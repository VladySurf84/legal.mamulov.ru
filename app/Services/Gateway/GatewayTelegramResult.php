<?php

namespace App\Services\Gateway;

readonly class GatewayTelegramResult
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $status,
        public array $payload,
    ) {}

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300 && ($this->payload['ok'] ?? false) === true;
    }

    public function code(): ?string
    {
        $code = $this->payload['code'] ?? null;

        return is_string($code) ? $code : null;
    }

    public function message(): string
    {
        $message = $this->payload['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : 'Gateway Telegram request failed.';
    }

    public function sentMessageId(): ?int
    {
        $id = is_array($this->payload['data'] ?? null)
            ? ($this->payload['data']['telegram_sent_message_id'] ?? null)
            : null;

        return is_int($id) ? $id : null;
    }
}
