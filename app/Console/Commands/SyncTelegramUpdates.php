<?php

namespace App\Console\Commands;

use App\Models\TelegramUpdate;
use App\Models\TelegramChat;
use App\Services\Telegram\TelegramLoginLinkService;
use App\Services\Telegram\TelegramService;
use Illuminate\Console\Command;
use Throwable;

class SyncTelegramUpdates extends Command
{
    protected $signature = 'telegram:sync-updates
        {--offset= : Telegram update offset}
        {--limit=100 : Maximum updates to fetch}
        {--timeout=0 : Telegram long polling timeout in seconds}';

    protected $description = 'Fetch Telegram bot updates and record chats that wrote to the bot.';

    public function handle(TelegramService $telegram, TelegramLoginLinkService $loginLinks): int
    {
        $offsetOption = $this->option('offset');
        $offset = is_numeric($offsetOption)
            ? (int) $offsetOption
            : $this->nextOffset();

        $updates = $telegram->getUpdates(
            $offset,
            max(1, min(100, (int) $this->option('limit'))),
            max(0, (int) $this->option('timeout')),
        );

        $recorded = 0;
        $ignored = 0;
        $linksSent = 0;

        foreach ($updates as $update) {
            $recordedUpdate = $telegram->recordUpdate($update);

            if ($recordedUpdate) {
                $recorded++;
                $linksSent += $this->sendLoginLinkIfNeeded($recordedUpdate, $loginLinks);
            } else {
                $ignored++;
            }
        }

        $this->info(sprintf(
            'Telegram updates sync complete: %d update(s), %d recorded, %d ignored, %d login link(s) sent, offset %s.',
            count($updates),
            $recorded,
            $ignored,
            $linksSent,
            $offset === null ? 'auto' : (string) $offset,
        ));

        return self::SUCCESS;
    }

    private function nextOffset(): ?int
    {
        $lastUpdateId = TelegramUpdate::query()->max('telegram_update_id');

        return $lastUpdateId === null ? null : ((int) $lastUpdateId + 1);
    }

    private function sendLoginLinkIfNeeded(TelegramUpdate $update, TelegramLoginLinkService $loginLinks): int
    {
        if (! $update->telegram_chat_id) {
            return 0;
        }

        $chat = TelegramChat::query()->find($update->telegram_chat_id);

        if (! $chat) {
            return 0;
        }

        try {
            $link = $loginLinks->sendLoginLink($chat);
        } catch (Throwable $exception) {
            $this->warn("Could not send Telegram login link to chat {$update->telegram_chat_id}: {$exception->getMessage()}");

            return 0;
        }

        return $link && $link->wasChanged('last_sent_at') ? 1 : 0;
    }
}
