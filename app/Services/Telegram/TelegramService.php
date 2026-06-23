<?php

namespace App\Services\Telegram;

use App\Models\TelegramSentMessage;
use App\Models\TelegramChat;
use App\Models\TelegramUpdate;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class TelegramService
{
    public const PARSE_MODE_HTML = 'HTML';
    public const PARSE_MODE_MARKDOWN = 'Markdown';
    public const PARSE_MODE_MARKDOWN_V2 = 'MarkdownV2';

    /**
     * @param string|array<int, string> $message
     */
    public function sendToUser(
        User $user,
        string|array $message,
        string $parseMode = self::PARSE_MODE_HTML,
        bool $disableWebPagePreview = false,
    ): TelegramSentMessage {
        $chatId = $user->telegram_chat_id;

        if (! is_string($chatId) || trim($chatId) === '') {
            throw new InvalidArgumentException("User {$user->email} has no telegram_chat_id.");
        }

        return $this->send($chatId, $message, $parseMode, $disableWebPagePreview, $user);
    }

    /**
     * @param string|array<int, string> $message
     */
    public function send(
        string $chatId,
        string|array $message,
        string $parseMode = self::PARSE_MODE_HTML,
        bool $disableWebPagePreview = false,
        ?User $user = null,
    ): TelegramSentMessage {
        $token = config('services.telegram.bot_token');

        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not configured.');
        }

        $text = is_array($message) ? implode("\n", $message) : $message;

        $log = TelegramSentMessage::query()->create([
            'user_id' => $user?->getKey(),
            'telegram_chat_id' => $chatId,
            'message' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => $disableWebPagePreview,
            'sent_at' => now(),
        ]);

        try {
            $response = Http::timeout((int) config('services.telegram.timeout', 10))
                ->asForm()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => $parseMode,
                    'disable_web_page_preview' => $disableWebPagePreview,
                ]);

            $log->forceFill([
                'http_code' => $response->status(),
                'response_body' => $response->body(),
                'error_message' => $response->successful() ? null : $response->body(),
            ])->save();
        } catch (ConnectionException $exception) {
            $log->forceFill([
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }

        return $log;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpdates(?int $offset = null, int $limit = 100, int $timeout = 0): array
    {
        $token = config('services.telegram.bot_token');

        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not configured.');
        }

        $payload = array_filter([
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message', 'edited_message'], JSON_THROW_ON_ERROR),
        ], static fn ($value) => $value !== null);

        $response = Http::timeout(max($timeout + 5, (int) config('services.telegram.timeout', 10)))
            ->get("https://api.telegram.org/bot{$token}/getUpdates", $payload);

        if (! $response->successful()) {
            throw new RuntimeException("Telegram getUpdates failed with HTTP {$response->status()}: {$response->body()}");
        }

        $data = $response->json();

        if (! is_array($data) || ($data['ok'] ?? false) !== true || ! is_array($data['result'] ?? null)) {
            throw new RuntimeException('Telegram getUpdates returned unexpected response.');
        }

        return $data['result'];
    }

    /**
     * @param array<string, mixed> $update
     */
    public function recordUpdate(array $update): ?TelegramUpdate
    {
        $updateId = $update['update_id'] ?? null;

        if (! is_int($updateId) && ! ctype_digit((string) $updateId)) {
            return null;
        }

        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if (! is_array($message)) {
            return null;
        }

        $chat = $message['chat'] ?? null;

        if (! is_array($chat) || ! array_key_exists('id', $chat)) {
            return null;
        }

        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $chatId = (string) $chat['id'];
        $messageDate = isset($message['date'])
            ? Carbon::createFromTimestamp((int) $message['date'], 'UTC')
            : now('UTC');
        $text = is_string($message['text'] ?? null) ? $message['text'] : null;

        TelegramChat::query()->updateOrCreate(
            ['telegram_chat_id' => $chatId],
            [
                'telegram_user_id' => isset($from['id']) ? (string) $from['id'] : null,
                'type' => is_string($chat['type'] ?? null) ? $chat['type'] : null,
                'username' => is_string($chat['username'] ?? null) ? $chat['username'] : null,
                'first_name' => is_string($chat['first_name'] ?? null) ? $chat['first_name'] : (is_string($from['first_name'] ?? null) ? $from['first_name'] : null),
                'last_name' => is_string($chat['last_name'] ?? null) ? $chat['last_name'] : (is_string($from['last_name'] ?? null) ? $from['last_name'] : null),
                'title' => is_string($chat['title'] ?? null) ? $chat['title'] : null,
                'last_update_id' => (int) $updateId,
                'last_message_text' => $text,
                'last_seen_at' => $messageDate,
                'is_active' => true,
                'raw_chat' => $chat,
                'raw_from' => $from,
            ],
        );

        return TelegramUpdate::query()->updateOrCreate(
            ['telegram_update_id' => (int) $updateId],
            [
                'telegram_chat_id' => $chatId,
                'message_text' => $text,
                'update_type' => array_key_exists('edited_message', $update) ? 'edited_message' : 'message',
                'payload' => $update,
                'received_at' => now('UTC'),
            ],
        );
    }

    public static function slashes(string $text): string
    {
        return addcslashes($text, '_*[]()~`>#+-=|{}.!');
    }
}
