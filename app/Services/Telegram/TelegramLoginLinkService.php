<?php

namespace App\Services\Telegram;

use App\Models\TelegramChat;
use App\Models\TelegramLoginLink;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class TelegramLoginLinkService
{
    public function __construct(
        private readonly TelegramService $telegram,
    ) {
    }

    public function ensureLink(TelegramChat $chat): TelegramLoginLink
    {
        $activeLink = TelegramLoginLink::query()
            ->where('telegram_chat_id', $chat->getKey())
            ->whereNull('used_at')
            ->where('expires_at', '>', now('UTC'))
            ->latest('telegram_login_link_id')
            ->first();

        if ($activeLink) {
            return $activeLink;
        }

        return TelegramLoginLink::query()->create([
            'telegram_chat_id' => $chat->getKey(),
            'token' => Str::random(48),
            'expires_at' => now('UTC')->addDay(),
        ]);
    }

    public function sendLoginLink(TelegramChat $chat): ?TelegramLoginLink
    {
        if ($this->chatHasUser($chat)) {
            return null;
        }

        $link = $this->ensureLink($chat);

        if ($link->last_sent_at && $link->last_sent_at->gt(now('UTC')->subMinutes(5))) {
            return $link;
        }

        $this->telegram->send(
            (string) $chat->getKey(),
            [
                'Чтобы подключить Telegram к legal.mamulov.ru, войдите через Google:',
                route('login', ['telegram_link' => $link->token]),
            ],
            TelegramService::PARSE_MODE_HTML,
            true,
        );

        $link->forceFill(['last_sent_at' => now('UTC')])->save();

        return $link;
    }

    public function attachUserByToken(User $user, string $token): TelegramLoginLink
    {
        $link = TelegramLoginLink::query()
            ->where('token', $token)
            ->whereNull('used_at')
            ->first();

        if (! $link || ! $link->isUsable()) {
            throw new RuntimeException('Telegram login link is expired or already used.');
        }

        $user->forceFill([
            'telegram_chat_id' => $link->telegram_chat_id,
        ])->save();

        $link->forceFill([
            'user_id' => $user->getKey(),
            'used_at' => now('UTC'),
        ])->save();

        return $link;
    }

    private function chatHasUser(TelegramChat $chat): bool
    {
        return User::query()
            ->where('telegram_chat_id', $chat->getKey())
            ->exists();
    }
}
