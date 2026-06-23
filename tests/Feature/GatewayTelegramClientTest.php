<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Gateway\GatewayTelegramClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewayTelegramClientTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $testEmails = [];

    protected function tearDown(): void
    {
        if ($this->testEmails !== []) {
            User::query()
                ->whereIn('email', $this->testEmails)
                ->delete();
        }

        parent::tearDown();
    }

    public function test_it_updates_user_telegram_chat_id_after_successful_gateway_send(): void
    {
        config([
            'services.mamulov_gateway.base_url' => 'https://mamulov.com',
            'services.mamulov_gateway.telegram_api_token' => 'gateway-token',
        ]);

        $email = 'gateway-success-'.uniqid('', true).'@example.com';
        $this->testEmails[] = $email;

        $user = User::query()->create([
            'name' => 'Gateway User',
            'email' => $email,
            'password' => null,
            'is_active' => true,
            'telegram_chat_id' => null,
        ]);

        $chatId = 'test-success-'.$user->getKey();

        Http::fake([
            'https://mamulov.com/api/telegram/messages' => Http::response([
                'ok' => true,
                'data' => [
                    'telegram_sent_message_id' => 42,
                    'telegram_chat_id' => $chatId,
                    'user_id' => 7,
                    'http_code' => 200,
                    'sent_at' => now()->toISOString(),
                    'error_message' => null,
                ],
            ], 201),
        ]);

        $result = app(GatewayTelegramClient::class)
            ->sendToUser($user, 'Hello from legal');

        $this->assertTrue($result->successful());
        $this->assertSame($chatId, $user->refresh()->telegram_chat_id);
    }

    public function test_it_clears_user_telegram_chat_id_when_gateway_returns_not_connected(): void
    {
        config([
            'services.mamulov_gateway.base_url' => 'https://mamulov.com',
            'services.mamulov_gateway.telegram_api_token' => 'gateway-token',
        ]);

        $email = 'gateway-not-connected-'.uniqid('', true).'@example.com';
        $this->testEmails[] = $email;
        $oldChatId = 'test-old-'.uniqid('', true);

        $user = User::query()->create([
            'name' => 'Gateway User',
            'email' => $email,
            'password' => null,
            'is_active' => true,
            'telegram_chat_id' => $oldChatId,
        ]);

        Http::fake([
            'https://mamulov.com/api/telegram/messages' => Http::response([
                'ok' => false,
                'code' => GatewayTelegramClient::CODE_TELEGRAM_NOT_CONNECTED,
                'message' => 'У пользователя не привязан Telegram.',
                'data' => [
                    'user' => $email,
                    'telegram_connected' => false,
                ],
            ], 409),
        ]);

        $result = app(GatewayTelegramClient::class)
            ->sendToUser($user, 'Hello from legal');

        $this->assertFalse($result->successful());
        $this->assertSame(GatewayTelegramClient::CODE_TELEGRAM_NOT_CONNECTED, $result->code());
        $this->assertNull($user->refresh()->telegram_chat_id);
    }
}
