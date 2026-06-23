<?php

namespace App\Services\Telegram;

use App\Models\TelegramChat;
use App\Models\TelegramLoginLink;
use App\Models\TelegramUpdate;

class TelegramUpdateHandler
{
    public function __construct(
        private readonly TelegramService $telegram,
        private readonly TelegramLoginLinkService $loginLinks,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     */
    public function handle(array $update): TelegramUpdateResult
    {
        $recordedUpdate = $this->telegram->recordUpdate($update);

        if (! $recordedUpdate || ! $recordedUpdate->telegram_chat_id) {
            return new TelegramUpdateResult($recordedUpdate, null);
        }

        $chat = TelegramChat::query()->find($recordedUpdate->telegram_chat_id);

        if (! $chat) {
            return new TelegramUpdateResult($recordedUpdate, null);
        }

        return new TelegramUpdateResult(
            $recordedUpdate,
            $this->loginLinks->sendLoginLink($chat),
        );
    }
}
