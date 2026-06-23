<?php

namespace App\Services\ExchangeRates;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class KyrgyzBankExchangeRateSyncService
{
    private const RATE_CURRENCY_CODE = 'KGS';

    /**
     * @return array{sync_run_id: int, providers: int, quotes: int, intervals_opened: int, intervals_updated: int, intervals_closed: int}
     */
    public function sync(array $providers = ['mbank', 'obank'], array $runContext = []): array
    {
        $runId = $this->startRun($runContext);
        $summary = [
            'sync_run_id' => $runId,
            'providers' => 0,
            'quotes' => 0,
            'intervals_opened' => 0,
            'intervals_updated' => 0,
            'intervals_closed' => 0,
        ];

        try {
            foreach ($providers as $provider) {
                $provider = strtolower(trim((string) $provider));

                if ($provider === '') {
                    continue;
                }

                $observedAt = now();
                $quotes = match ($provider) {
                    'mbank' => $this->fetchMbank($runId, $observedAt),
                    'obank' => $this->fetchObank($runId, $observedAt),
                    default => throw new RuntimeException("Unknown exchange rate provider [{$provider}]."),
                };

                $providerSummary = $this->persistProviderQuotes($provider, $quotes, $observedAt);

                $summary['providers']++;
                $summary['quotes'] += count($quotes);
                $summary['intervals_opened'] += $providerSummary['opened'];
                $summary['intervals_updated'] += $providerSummary['updated'];
                $summary['intervals_closed'] += $providerSummary['closed'];
            }

            $this->finishRun($runId, 'success', $summary);

            return $summary;
        } catch (Throwable $exception) {
            $this->finishRun($runId, 'failed', $summary, $exception);

            throw $exception;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchMbank(int $runId, Carbon $observedAt): array
    {
        $url = 'https://mbank.kg/exchange_rates';
        $body = $this->request($runId, 'mbank', 'GET', '/exchange_rates', $url);

        if (! preg_match('/<script\s+id="__NEXT_DATA__"\s+type="application\/json">(.*?)<\/script>/s', $body, $matches)) {
            throw new RuntimeException('MBank exchange rates payload does not contain __NEXT_DATA__.');
        }

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
        $groups = data_get($payload, 'props.pageProps.exchangePage.cash_exchange', []);

        if (! is_array($groups)) {
            throw new RuntimeException('MBank exchange rates payload has unexpected structure.');
        }

        $quotes = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $rateType = $this->mbankRateType((string) data_get($group, 'operation_type', ''));
            $bankValidFrom = $this->parseBankDate(data_get($group, 'actual_from'));

            foreach ((array) data_get($group, 'values', []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $currency = strtoupper(trim((string) data_get($item, 'currency', '')));

                if (! $this->isCurrencyCode($currency)) {
                    continue;
                }

                $buyRate = $this->decimal(data_get($item, 'buy'));
                $sellRate = $this->decimal(data_get($item, 'sell'));
                $officialRate = $this->decimal(data_get($item, 'nbkr'));

                if (! $this->hasMeaningfulRate($buyRate, $sellRate, $officialRate)) {
                    continue;
                }

                $quotes[] = $this->quote(
                    provider: 'mbank',
                    rateType: $rateType,
                    currencyCode: $currency,
                    buyRate: $buyRate,
                    sellRate: $sellRate,
                    officialRate: $officialRate,
                    bankValidFrom: $bankValidFrom,
                    observedAt: $observedAt,
                    rawItem: $item + ['operation_type' => data_get($group, 'operation_type')]
                );
            }
        }

        return $quotes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchObank(int $runId, Carbon $observedAt): array
    {
        $url = 'https://obank.kg/api/exchange-rates';
        $body = $this->request($runId, 'obank', 'GET', '/api/exchange-rates', $url);
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new RuntimeException('Obank exchange rates payload has unexpected structure.');
        }

        $groups = [
            'non_cash' => $payload['NONCASH'] ?? $payload['noncash'] ?? [],
            'cash' => $payload['CASH'] ?? $payload['cash'] ?? [],
            'official' => $payload['CB'] ?? $payload['NBKR'] ?? $payload['cb'] ?? $payload['nbkr'] ?? [],
        ];

        $quotes = [];

        foreach ($groups as $rateType => $rows) {
            foreach ((array) $rows as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $currency = strtoupper(trim((string) data_get($item, 'currency', '')));

                if (! $this->isCurrencyCode($currency)) {
                    continue;
                }

                $buyRate = $this->decimal(data_get($item, 'buyingRate'));
                $sellRate = $this->decimal(data_get($item, 'sellingRate'));
                $officialRate = $rateType === 'official' ? ($sellRate ?? $buyRate) : null;

                if (! $this->hasMeaningfulRate(
                    $rateType === 'official' ? null : $buyRate,
                    $rateType === 'official' ? null : $sellRate,
                    $officialRate,
                )) {
                    continue;
                }

                $quotes[] = $this->quote(
                    provider: 'obank',
                    rateType: $rateType,
                    currencyCode: $currency,
                    buyRate: $rateType === 'official' ? null : $buyRate,
                    sellRate: $rateType === 'official' ? null : $sellRate,
                    officialRate: $officialRate,
                    bankValidFrom: $this->parseBankDate(data_get($item, 'actualDate')),
                    observedAt: $observedAt,
                    rawItem: $item
                );
            }
        }

        return $quotes;
    }

    private function request(int $runId, string $provider, string $method, string $endpoint, string $url): string
    {
        $startedAt = microtime(true);
        $status = null;
        $body = null;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'legal.mamulov.ru exchange-rates sync',
                    'Accept' => '*/*',
                ])
                ->send($method, $url);

            $status = $response->status();
            $body = $response->body();

            $this->logRequest($runId, $provider, $method, $endpoint, $url, $status, $body, $startedAt);

            if ($status < 200 || $status >= 300) {
                throw new RuntimeException("{$provider} exchange rates request failed with status {$status}.");
            }

            return $body;
        } catch (Throwable $exception) {
            $this->logRequest($runId, $provider, $method, $endpoint, $url, $status, $body, $startedAt, $exception);

            throw $exception;
        }
    }

    private function logRequest(
        int $runId,
        string $provider,
        string $method,
        string $endpoint,
        string $url,
        ?int $status,
        ?string $body,
        float $startedAt,
        ?Throwable $exception = null,
    ): void {
        static $logged = [];

        $key = implode('|', [$runId, $provider, $method, $endpoint, (string) $startedAt]);

        if (isset($logged[$key])) {
            return;
        }

        $logged[$key] = true;
        $now = now();
        $jsonBody = $this->jsonBody($body);

        DB::insert(<<<'SQL'
INSERT INTO legal.api_sync_requests (
    api_sync_run_id,
    provider,
    method,
    endpoint,
    url,
    params,
    http_status,
    duration_ms,
    response_hash,
    response_body,
    response_json,
    error,
    requested_at,
    created_at,
    updated_at
) VALUES (
    ?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?, ?, CASE WHEN ?::text IS NULL THEN NULL ELSE ?::jsonb END, ?, ?, ?, ?
)
SQL, [
            $runId,
            $provider,
            $method,
            $endpoint,
            $url,
            json_encode([], JSON_THROW_ON_ERROR),
            $status,
            (int) round((microtime(true) - $startedAt) * 1000),
            $body !== null ? hash('sha256', $body) : null,
            $body,
            $jsonBody,
            $jsonBody,
            $exception?->getMessage() ?: ($status !== null && ($status < 200 || $status >= 300) ? $body : null),
            $now,
            $now,
            $now,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $quotes
     * @return array{opened: int, updated: int, closed: int}
     */
    private function persistProviderQuotes(string $provider, array $quotes, Carbon $observedAt): array
    {
        $summary = ['opened' => 0, 'updated' => 0, 'closed' => 0];
        $seenIdentityKeys = [];

        DB::transaction(function () use ($provider, $quotes, $observedAt, &$summary, &$seenIdentityKeys): void {
            foreach ($quotes as $quote) {
                $sourceRecordId = $this->insertSourceRecord($quote);
                $this->insertSourceQuote($sourceRecordId, $quote);

                $identityKey = $this->identityKey($quote);
                $seenIdentityKeys[$identityKey] = true;
                $quoteHash = $this->quoteHash($quote);

                $current = DB::table('legal.exchange_rates')
                    ->where('provider', $quote['provider'])
                    ->where('rate_type', $quote['rate_type'])
                    ->where('currency_code', $quote['currency_code'])
                    ->where('rate_currency_code', $quote['rate_currency_code'])
                    ->whereNull('observed_to')
                    ->lockForUpdate()
                    ->first();

                if ($current === null) {
                    $this->insertExchangeRateInterval($quote, $quoteHash, $sourceRecordId);
                    $summary['opened']++;

                    continue;
                }

                if ($current->quote_hash === $quoteHash) {
                    DB::table('legal.exchange_rates')
                        ->where('exchange_rate_id', $current->exchange_rate_id)
                        ->update([
                            'last_seen_at' => $quote['observed_at'],
                            'last_source_record_id' => $sourceRecordId,
                            'bank_valid_from' => $quote['bank_valid_from'] ?? $current->bank_valid_from,
                            'updated_at' => now(),
                        ]);

                    $summary['updated']++;

                    continue;
                }

                DB::table('legal.exchange_rates')
                    ->where('exchange_rate_id', $current->exchange_rate_id)
                    ->update([
                        'observed_to' => $quote['observed_at'],
                        'last_seen_at' => $quote['observed_at'],
                        'last_source_record_id' => $sourceRecordId,
                        'updated_at' => now(),
                    ]);

                $this->insertExchangeRateInterval($quote, $quoteHash, $sourceRecordId);
                $summary['closed']++;
                $summary['opened']++;
            }

            if ($quotes !== []) {
                $openRows = DB::table('legal.exchange_rates')
                    ->where('provider', $provider)
                    ->whereNull('observed_to')
                    ->lockForUpdate()
                    ->get();

                foreach ($openRows as $row) {
                    $identityKey = implode('|', [
                        $row->provider,
                        $row->rate_type,
                        trim((string) $row->currency_code),
                        trim((string) $row->rate_currency_code),
                    ]);

                    if (isset($seenIdentityKeys[$identityKey])) {
                        continue;
                    }

                    DB::table('legal.exchange_rates')
                        ->where('exchange_rate_id', $row->exchange_rate_id)
                        ->update([
                            'observed_to' => $observedAt,
                            'last_seen_at' => $observedAt,
                            'updated_at' => now(),
                        ]);

                    $summary['closed']++;
                }
            }
        });

        return $summary;
    }

    /**
     * @param array<string, mixed> $quote
     */
    private function insertSourceRecord(array $quote): int
    {
        $rawPayload = $this->json($quote['raw_item']);
        $externalHash = hash('sha256', implode('|', [
            $quote['provider'],
            $quote['rate_type'],
            $quote['currency_code'],
            $quote['rate_currency_code'],
            $quote['observed_at'],
            $rawPayload,
        ]));
        $externalId = implode(':', [
            $quote['provider'],
            $quote['rate_type'],
            $quote['currency_code'],
            str_replace([' ', ':'], ['T', '-'], (string) $quote['observed_at']),
        ]);
        $now = now();

        $row = DB::selectOne(<<<'SQL'
INSERT INTO legal.source_records (
    source_system,
    source_channel,
    source_record_type,
    external_id,
    external_hash,
    received_at,
    recorded_at,
    raw_payload,
    metadata,
    created_at,
    updated_at
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, ?, ?
)
ON CONFLICT (source_system, source_record_type, external_hash)
WHERE external_hash IS NOT NULL
DO UPDATE SET
    received_at = EXCLUDED.received_at,
    recorded_at = EXCLUDED.recorded_at,
    updated_at = EXCLUDED.updated_at
RETURNING source_record_id
SQL, [
            $quote['provider'],
            'bank_exchange_rates',
            'exchange_rate_quote',
            $externalId,
            $externalHash,
            $quote['observed_at'],
            $quote['observed_at'],
            $rawPayload,
            $this->json([
                'rate_type' => $quote['rate_type'],
                'currency_code' => $quote['currency_code'],
                'rate_currency_code' => $quote['rate_currency_code'],
            ]),
            $now,
            $now,
        ]);

        return (int) $row->source_record_id;
    }

    /**
     * @param array<string, mixed> $quote
     */
    private function insertSourceQuote(int $sourceRecordId, array $quote): void
    {
        $now = now();

        DB::insert(<<<'SQL'
INSERT INTO legal.source_exchange_rate_quotes (
    source_record_id,
    provider,
    rate_type,
    currency_code,
    rate_currency_code,
    buy_rate,
    sell_rate,
    official_rate,
    bank_valid_from,
    observed_at,
    raw_item,
    created_at,
    updated_at
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?
)
ON CONFLICT (source_record_id)
DO UPDATE SET
    buy_rate = EXCLUDED.buy_rate,
    sell_rate = EXCLUDED.sell_rate,
    official_rate = EXCLUDED.official_rate,
    bank_valid_from = EXCLUDED.bank_valid_from,
    observed_at = EXCLUDED.observed_at,
    raw_item = EXCLUDED.raw_item,
    updated_at = EXCLUDED.updated_at
SQL, [
            $sourceRecordId,
            $quote['provider'],
            $quote['rate_type'],
            $quote['currency_code'],
            $quote['rate_currency_code'],
            $quote['buy_rate'],
            $quote['sell_rate'],
            $quote['official_rate'],
            $quote['bank_valid_from'],
            $quote['observed_at'],
            $this->json($quote['raw_item']),
            $now,
            $now,
        ]);
    }

    /**
     * @param array<string, mixed> $quote
     */
    private function insertExchangeRateInterval(array $quote, string $quoteHash, int $sourceRecordId): void
    {
        $now = now();

        DB::table('legal.exchange_rates')->insert([
            'provider' => $quote['provider'],
            'rate_type' => $quote['rate_type'],
            'currency_code' => $quote['currency_code'],
            'rate_currency_code' => $quote['rate_currency_code'],
            'buy_rate' => $quote['buy_rate'],
            'sell_rate' => $quote['sell_rate'],
            'official_rate' => $quote['official_rate'],
            'bank_valid_from' => $quote['bank_valid_from'],
            'observed_from' => $quote['observed_at'],
            'observed_to' => null,
            'first_seen_at' => $quote['observed_at'],
            'last_seen_at' => $quote['observed_at'],
            'first_source_record_id' => $sourceRecordId,
            'last_source_record_id' => $sourceRecordId,
            'quote_hash' => $quoteHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $quote
     */
    private function identityKey(array $quote): string
    {
        return implode('|', [
            $quote['provider'],
            $quote['rate_type'],
            $quote['currency_code'],
            $quote['rate_currency_code'],
        ]);
    }

    /**
     * @param array<string, mixed> $quote
     */
    private function quoteHash(array $quote): string
    {
        return hash('sha256', $this->json([
            'provider' => $quote['provider'],
            'rate_type' => $quote['rate_type'],
            'currency_code' => $quote['currency_code'],
            'rate_currency_code' => $quote['rate_currency_code'],
            'buy_rate' => $quote['buy_rate'],
            'sell_rate' => $quote['sell_rate'],
            'official_rate' => $quote['official_rate'],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(
        string $provider,
        string $rateType,
        string $currencyCode,
        ?string $buyRate,
        ?string $sellRate,
        ?string $officialRate,
        ?Carbon $bankValidFrom,
        Carbon $observedAt,
        array $rawItem,
    ): array {
        return [
            'provider' => $provider,
            'rate_type' => $rateType,
            'currency_code' => $currencyCode,
            'rate_currency_code' => self::RATE_CURRENCY_CODE,
            'buy_rate' => $buyRate,
            'sell_rate' => $sellRate,
            'official_rate' => $officialRate,
            'bank_valid_from' => $bankValidFrom?->timezone('UTC')->format('Y-m-d H:i:s'),
            'observed_at' => $observedAt->timezone('UTC')->format('Y-m-d H:i:s'),
            'raw_item' => $rawItem,
        ];
    }

    private function mbankRateType(string $operationType): string
    {
        return str_contains(mb_strtolower($operationType), 'безнал') ? 'non_cash' : 'cash';
    }

    private function decimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], (string) $value);

        return is_numeric($normalized) ? $normalized : null;
    }

    private function parseBankDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function isCurrencyCode(string $value): bool
    {
        return (bool) preg_match('/^[A-Z]{3}$/', $value);
    }

    private function hasMeaningfulRate(?string ...$rates): bool
    {
        foreach ($rates as $rate) {
            if ($rate !== null && (float) $rate > 0) {
                return true;
            }
        }

        return false;
    }

    private function jsonBody(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE ? $body : null;
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function startRun(array $runContext): int
    {
        $now = now();

        $row = DB::selectOne(<<<'SQL'
INSERT INTO legal.api_sync_runs (
    provider,
    type,
    status,
    started_by_type,
    started_by_user_id,
    started_from,
    started_at,
    created_at,
    updated_at
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?
)
RETURNING api_sync_run_id
SQL, [
            'kgs_exchange_rates',
            'exchange_rates_sync',
            'started',
            $runContext['started_by_type'] ?? (app()->runningInConsole() ? 'console' : 'user'),
            $runContext['started_by_user_id'] ?? null,
            $runContext['started_from'] ?? (app()->runningInConsole() ? 'cli' : 'web'),
            $now,
            $now,
            $now,
        ]);

        return (int) $row->api_sync_run_id;
    }

    /**
     * @param array<string, int> $summary
     */
    private function finishRun(int $runId, string $status, array $summary, ?Throwable $exception = null): void
    {
        DB::table('legal.api_sync_runs')
            ->where('api_sync_run_id', $runId)
            ->update([
                'status' => $status,
                'operations_count' => $summary['quotes'] ?? 0,
                'requests_count' => DB::table('legal.api_sync_requests')
                    ->where('api_sync_run_id', $runId)
                    ->count(),
                'error' => $exception?->getMessage(),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
