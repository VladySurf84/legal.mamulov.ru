<?php

namespace App\Services\Gateway;

use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GatewayTelegramClient
{
    public const CODE_TELEGRAM_NOT_CONNECTED = 'telegram_not_connected';

    /**
     * @param string|array<int, string> $message
     */
    public function sendToUser(
        User $user,
        string|array $message,
        string $parseMode = 'HTML',
        bool $disableWebPagePreview = false,
    ): GatewayTelegramResult {
        $response = Http::timeout((int) config('services.mamulov_gateway.timeout', 10))
            ->withToken($this->token())
            ->acceptJson()
            ->post($this->endpoint('/api/telegram/messages'), [
                'user' => $user->email,
                'message' => is_array($message) ? implode("\n", $message) : $message,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => $disableWebPagePreview,
            ]);

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        $this->syncTelegramStatus($user, $response, $payload);

        return new GatewayTelegramResult($response->status(), $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function syncTelegramStatus(User $user, Response $response, array $payload): void
    {
        if ($response->created() && is_array($payload['data'] ?? null)) {
            $chatId = $payload['data']['telegram_chat_id'] ?? null;

            if (is_string($chatId) && trim($chatId) !== '') {
                $user->forceFill(['telegram_chat_id' => trim($chatId)])->save();
            }

            return;
        }

        if ($response->status() === 409 && ($payload['code'] ?? null) === self::CODE_TELEGRAM_NOT_CONNECTED) {
            $user->forceFill(['telegram_chat_id' => null])->save();
        }
    }

    private function endpoint(string $path): string
    {
        $baseUrl = rtrim((string) config('services.mamulov_gateway.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('MAMULOV_GATEWAY_API_BASE_URL is not configured.');
        }

        return $baseUrl.'/'.ltrim($path, '/');
    }

    private function token(): string
    {
        $token = (string) config('services.mamulov_gateway.telegram_api_token');

        if ($token === '') {
            throw new RuntimeException('MAMULOV_GATEWAY_TELEGRAM_API_TOKEN is not configured.');
        }

        return $token;
    }
}
