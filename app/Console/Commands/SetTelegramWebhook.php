<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook
        {--url= : Explicit webhook URL}
        {--drop : Delete Telegram webhook instead of setting it}';

    protected $description = 'Set or delete Telegram bot webhook.';

    public function handle(): int
    {
        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }

        if ((bool) $this->option('drop')) {
            $response = Http::timeout((int) config('services.telegram.timeout', 10))
                ->asForm()
                ->post("https://api.telegram.org/bot{$token}/deleteWebhook", [
                    'drop_pending_updates' => false,
                ]);

            return $this->reportTelegramResponse($response->json(), 'Telegram webhook deleted.');
        }

        $url = $this->option('url');
        $url = is_string($url) && trim($url) !== '' ? trim($url) : $this->webhookUrl();

        $response = Http::timeout((int) config('services.telegram.timeout', 10))
            ->asForm()
            ->post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $url,
                'allowed_updates' => json_encode(['message', 'edited_message'], JSON_THROW_ON_ERROR),
                'drop_pending_updates' => false,
            ]);

        return $this->reportTelegramResponse($response->json(), "Telegram webhook set to {$url}.");
    }

    private function webhookUrl(): string
    {
        $secret = (string) config('services.telegram.webhook_secret');

        if ($secret === '') {
            throw new RuntimeException('TELEGRAM_WEBHOOK_SECRET is not configured.');
        }

        return rtrim((string) config('app.url'), '/').'/api/telegram/webhook/'.Str::of($secret)->trim('/');
    }

    /**
     * @param mixed $payload
     */
    private function reportTelegramResponse(mixed $payload, string $successMessage): int
    {
        if (! is_array($payload) || ($payload['ok'] ?? false) !== true) {
            $this->error('Telegram API returned an error.');
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return self::FAILURE;
        }

        $this->info($successMessage);
        $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
