<?php

namespace Tests\Feature;

use App\Services\ExchangeRates\KompanionRubAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KompanionRubAlertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        foreach (['2026_11_15_120000_create_exchange_rate_bank_settings.php' => 'exchange_rate_bank_settings', '2026_11_15_130000_create_exchange_rate_alert_states.php' => 'exchange_rate_alert_states'] as $migration => $table) {
            if (! Schema::hasTable('legal.'.$table)) {
                (require database_path('migrations/'.$migration))->up();
            }
        }
        config(['services.mamulov_gateway.base_url' => 'https://gateway.test', 'services.mamulov_gateway.telegram_api_token' => 'test-token', 'exchange_rates.kompanion_rub_recipient' => 'recipient@example.com']);
        Http::preventStrayRequests();
        Http::fake(['gateway.test/*' => Http::response(['ok' => true], 201)]);
        DB::table('legal.exchange_rate_bank_settings')->delete();
        foreach (['kompanion', 'mbank', 'obank'] as $provider) {
            DB::table('legal.exchange_rate_bank_settings')->insert(['provider' => $provider, 'accepts_rubles' => true]);
        }
        DB::table('legal.exchange_rates')->where('currency_code', 'RUB')->where('rate_currency_code', 'KGS')
            ->whereNull('valid_to')->update(['valid_to' => DB::raw('valid_from')]);
        foreach (['kompanion' => '1.1', 'mbank' => '1.02', 'obank' => '1.03', 'bakai' => '9'] as $provider => $rate) {
            $this->insertRate($provider, $rate);
        }
        $this->insertRate('obank', '9', 'cash');
        DB::table('legal.exchange_rate_alert_states')->where('alert_key', 'kompanion_non_cash_rub')
            ->update(['last_buy_rate' => '1.0', 'pending' => false, 'sent_at' => null]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_changed_highest_rate_sends_selected_bank_rates_through_gateway_once(): void
    {
        $this->check();
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/telegram/messages'
            && $request['user'] === 'recipient@example.com'
            && $request['message'] === "Курс покупки рубля у Компаньон 1,1\n\nПрием рублей (безналичный курс покупки, KGS за 1 RUB):\nКомпаньон — 1,1\nMBank — 1,02\nObank — 1,03"
            && $request->hasHeader('Authorization', 'Bearer test-token'));
        $this->check();
        Http::assertSentCount(1);
        $this->assertFalse((bool) $this->state()->pending);
        $this->assertNotNull($this->state()->sent_at);
    }

    public function test_unchanged_buy_rate_does_not_send_even_when_sell_rate_changes(): void
    {
        DB::table('legal.exchange_rate_alert_states')->update(['last_buy_rate' => '1.10000000']);
        $this->rateQuery('kompanion')->update(['sell_rate' => '2.0', 'quote_hash' => str_repeat('b', 64)]);
        $this->check();
        Http::assertNothingSent();
    }

    public function test_equal_or_higher_selected_rate_suppresses_notification(): void
    {
        foreach (['1.1', '1.2'] as $otherRate) {
            DB::table('legal.exchange_rate_alert_states')->update(['last_buy_rate' => '1.0']);
            $this->rateQuery('obank')->update(['buy_rate' => $otherRate]);
            $this->check();
        }
        Http::assertNothingSent();
    }

    public function test_missing_selected_bank_rate_suppresses_notification(): void
    {
        $this->rateQuery('obank')->update(['buy_rate' => null, 'sell_rate' => '1.2']);
        $this->check();
        Http::assertNothingSent();
    }

    public function test_no_other_selected_banks_suppresses_notification(): void
    {
        DB::table('legal.exchange_rate_bank_settings')->where('provider', '!=', 'kompanion')->update(['accepts_rubles' => false]);
        $this->check();
        Http::assertNothingSent();
    }

    public function test_first_observation_is_only_a_baseline(): void
    {
        DB::table('legal.exchange_rate_alert_states')->update(['last_buy_rate' => null]);
        $this->check();
        Http::assertNothingSent();
        $this->assertEquals(1.1, (float) $this->state()->last_buy_rate);
    }

    public function test_a_decreased_rate_still_sends_if_it_remains_strictly_highest(): void
    {
        DB::table('legal.exchange_rate_alert_states')->update(['last_buy_rate' => '1.2']);
        $this->check();
        Http::assertSentCount(1);
    }

    public function test_failed_delivery_is_retried_without_another_rate_change(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['gateway.test/*' => Http::sequence()->push(['ok' => false], 503)->push(['ok' => true], 201)]);
        $this->check();
        $this->assertTrue((bool) $this->state()->pending);
        $this->assertNotNull($this->state()->last_error);
        $this->check();
        Http::assertSentCount(2);
        $this->assertFalse((bool) $this->state()->pending);
        $this->assertNull($this->state()->last_error);
    }

    public function test_pending_notification_is_cancelled_when_another_bank_catches_up(): void
    {
        DB::table('legal.exchange_rate_alert_states')->update(['last_buy_rate' => '1.1', 'pending' => true]);
        $this->rateQuery('obank')->update(['buy_rate' => '1.1']);
        $this->check();
        Http::assertNothingSent();
        $this->assertFalse((bool) $this->state()->pending);
    }

    private function check(): void
    {
        app(KompanionRubAlertService::class)->check();
    }

    private function state(): object
    {
        return DB::table('legal.exchange_rate_alert_states')->where('alert_key', 'kompanion_non_cash_rub')->first();
    }

    private function rateQuery(string $provider): \Illuminate\Database\Query\Builder
    {
        return DB::table('legal.exchange_rates')->where('provider', $provider)->where('rate_type', 'non_cash')
            ->where('currency_code', 'RUB')->where('rate_currency_code', 'KGS')->whereNull('valid_to');
    }

    private function insertRate(string $provider, string $rate, string $type = 'non_cash'): void
    {
        DB::table('legal.exchange_rates')->insert([
            'provider' => $provider, 'rate_type' => $type, 'currency_code' => 'RUB', 'rate_currency_code' => 'KGS',
            'buy_rate' => $rate, 'valid_from' => now(), 'observed_from' => now(),
            'first_seen_at' => now(), 'last_seen_at' => now(), 'quote_hash' => str_repeat('a', 64),
        ]);
    }
}
