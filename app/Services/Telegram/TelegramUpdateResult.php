<?php

namespace App\Services\Telegram;

use App\Models\TelegramLoginLink;
use App\Models\TelegramUpdate;

readonly class TelegramUpdateResult
{
    public function __construct(
        public ?TelegramUpdate $update,
        public ?TelegramLoginLink $loginLink,
    ) {
    }

    public function recorded(): bool
    {
        return $this->update !== null;
    }

    public function loginLinkSent(): bool
    {
        return $this->loginLink !== null && $this->loginLink->wasChanged('last_sent_at');
    }
}
