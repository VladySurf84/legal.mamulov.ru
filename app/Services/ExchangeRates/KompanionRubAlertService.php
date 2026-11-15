<?php

namespace App\Services\ExchangeRates;

use App\Services\Gateway\GatewayTelegramClient;
use App\Support\ExchangeRateBanks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class KompanionRubAlertService
{
    private const KEY = 'kompanion_non_cash_rub';

    public function __construct(private readonly GatewayTelegramClient $telegram) {}

    public function check(): void
    {
        DB::transaction(function (): void {
            // Serialize scheduled and manual syncs, including delivery retries.
            $state = DB::table('legal.exchange_rate_alert_states')
                ->where('alert_key', self::KEY)->lockForUpdate()->first();
            if ($state === null) {
                throw new RuntimeException('Exchange rate alert state is not initialized.');
            }

            $rates = DB::table('legal.exchange_rates')
                ->where('rate_type', 'non_cash')->where('currency_code', 'RUB')
                ->where('rate_currency_code', 'KGS')->whereNull('valid_to')
                ->pluck('buy_rate', 'provider')->all();
            $current = $rates['kompanion'] ?? null;
            if ($current === null) {
                return;
            }

            $selected = DB::table('legal.exchange_rate_bank_settings')
                ->where('accepts_rubles', true)->orderBy('provider')->pluck('provider')->all();
            $changed = $state->last_buy_rate !== null
                && $this->decimal($state->last_buy_rate) !== $this->decimal($current);
            $qualifies = $this->isHighest($current, $selected, $rates);
            $pending = ($changed || (bool) $state->pending) && $qualifies;
            $update = ['last_buy_rate' => $current, 'pending' => $pending, 'last_error' => null, 'updated_at' => now()];

            if ($pending) {
                try {
                    $recipient = trim((string) config('exchange_rates.kompanion_rub_recipient'));
                    if ($recipient === '') {
                        throw new RuntimeException('Exchange rate alert recipient is not configured.');
                    }
                    $result = $this->telegram->sendToRecipient($recipient, $this->message($current, $selected, $rates));
                    if (! $result->successful()) {
                        throw new RuntimeException('Telegram gateway rejected the exchange rate alert.');
                    }
                    $update['pending'] = false;
                    $update['sent_at'] = now();
                } catch (Throwable $exception) {
                    // Keep the pending alert for the next successful sync; do not fail quote acquisition.
                    $update['last_error'] = 'Telegram delivery failed; will retry on the next sync.';
                    Log::warning('Kompanion RUB alert delivery failed.', ['exception_type' => $exception::class]);
                }
            }

            DB::table('legal.exchange_rate_alert_states')->where('alert_key', self::KEY)->update($update);
        });
    }

    private function isHighest(string $current, array $selected, array $rates): bool
    {
        $others = array_values(array_diff($selected, ['kompanion']));
        if ($others === []) {
            return false;
        }
        foreach ($others as $provider) {
            // Missing rates cannot establish that Kompanion is strictly better.
            if (! isset($rates[$provider]) || (float) $current <= (float) $rates[$provider]) {
                return false;
            }
        }

        return true;
    }

    private function message(string $current, array $selected, array $rates): string
    {
        $lines = ['Курс покупки рубля у Компаньон '.$this->format($current), '', 'Прием рублей (безналичный курс покупки, KGS за 1 RUB):'];
        foreach ($selected as $provider) {
            $label = ExchangeRateBanks::LABELS[$provider] ?? $provider;
            $lines[] = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').' — '.$this->format($rates[$provider]);
        }

        return implode("\n", $lines);
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 8, '.', '');
    }

    private function format(string $value): string
    {
        return rtrim(rtrim(str_replace('.', ',', $this->decimal($value)), '0'), ',');
    }
}
