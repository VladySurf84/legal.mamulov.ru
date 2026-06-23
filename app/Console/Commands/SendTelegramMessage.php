<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Gateway\GatewayTelegramClient;
use App\Services\Telegram\TelegramService;
use Illuminate\Console\Command;

class SendTelegramMessage extends Command
{
    protected $signature = 'telegram:send
        {message : Message text}
        {--user= : User id or email}
        {--chat-id= : Telegram chat id}
        {--parse-mode=HTML : HTML, Markdown or MarkdownV2}
        {--disable-preview : Disable web page preview}';

    protected $description = 'Send a message through the configured Telegram bot.';

    public function handle(TelegramService $telegram, GatewayTelegramClient $gatewayTelegram): int
    {
        $message = (string) $this->argument('message');
        $parseMode = (string) $this->option('parse-mode');
        $disablePreview = (bool) $this->option('disable-preview');

        $userOption = $this->option('user');
        $chatId = $this->option('chat-id');

        if (is_string($userOption) && trim($userOption) !== '') {
            $user = $this->findUser($userOption);

            if (! $user) {
                $this->error("User {$userOption} was not found.");

                return self::FAILURE;
            }

            $result = $gatewayTelegram->sendToUser($user, $message, $parseMode, $disablePreview);

            if ($result->successful()) {
                $messageId = $result->sentMessageId();
                $suffix = $messageId ? " #{$messageId}" : '';

                $this->info("Gateway Telegram message{$suffix} sent to user {$user->email}.");

                return self::SUCCESS;
            }

            $this->error($result->message());

            return self::FAILURE;
        }

        if (! is_string($chatId) || trim($chatId) === '') {
            $chatId = config('services.telegram.default_chat_id');
        }

        if (! is_string($chatId) || trim($chatId) === '') {
            $this->error('Provide --user, --chat-id or TELEGRAM_DEFAULT_CHAT_ID.');

            return self::FAILURE;
        }

        $log = $telegram->send($chatId, $message, $parseMode, $disablePreview);

        $this->info("Telegram message #{$log->getKey()} sent to chat {$chatId}.");

        return $log->http_code && $log->http_code >= 400 ? self::FAILURE : self::SUCCESS;
    }

    private function findUser(string $value): ?User
    {
        $value = trim($value);

        return User::query()
            ->when(
                ctype_digit($value),
                fn ($query) => $query->whereKey((int) $value),
                fn ($query) => $query->whereRaw('lower(email) = ?', [strtolower($value)]),
            )
            ->first();
    }
}
