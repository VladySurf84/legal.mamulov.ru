<?php

namespace App\Console\Commands;

use App\Models\TelegramUpdate;
use App\Services\Telegram\TelegramService;
use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Console\Command;
use Throwable;

class SyncTelegramUpdates extends Command
{
    protected $signature = 'telegram:sync-updates
        {--offset= : Telegram update offset}
        {--limit=100 : Maximum updates to fetch}
        {--timeout=0 : Telegram long polling timeout in seconds}';

    protected $description = 'Fetch Telegram bot updates and record chats that wrote to the bot.';

    public function handle(TelegramService $telegram, TelegramUpdateHandler $handler): int
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
            try {
                $result = $handler->handle($update);
            } catch (Throwable $exception) {
                $this->warn("Could not handle Telegram update: {$exception->getMessage()}");
                $ignored++;

                continue;
            }

            if (! $result->recorded()) {
                $ignored++;

                continue;
            }

            $recorded++;
            $linksSent += $result->loginLinkSent() ? 1 : 0;
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
}
