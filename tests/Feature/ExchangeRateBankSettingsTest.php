<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ExchangeRateBanks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExchangeRateBankSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        if (! Schema::hasTable('legal.exchange_rate_bank_settings')) {
            (require database_path('migrations/2026_11_15_120000_create_exchange_rate_bank_settings.php'))->up();
        }
        $this->actingAs(new User(['id' => 1, 'is_admin' => true, 'is_active' => true]));
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_settings_are_saved_displayed_and_can_be_unchecked(): void
    {
        $banks = array_fill_keys(array_keys(ExchangeRateBanks::LABELS), array_fill_keys(array_keys(ExchangeRateBanks::SETTINGS), '0'));
        $banks['mbank']['accepts_rubles'] = '1';
        $banks['obank']['euro_acquiring'] = '1';
        $banks['bakai']['visa_direct'] = '1';

        $this->from(route('exchange-rates.index'))->put(route('exchange-rates.bank-settings.update'), ['banks' => $banks])
            ->assertRedirect(route('exchange-rates.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('legal.exchange_rate_bank_settings', ['provider' => 'mbank', 'accepts_rubles' => true, 'visa_direct' => false]);
        $this->assertDatabaseHas('legal.exchange_rate_bank_settings', ['provider' => 'obank', 'euro_acquiring' => true]);
        $this->assertDatabaseHas('legal.exchange_rate_bank_settings', ['provider' => 'bakai', 'visa_direct' => true]);

        $this->get(route('exchange-rates.index'))->assertOk()->assertSee('Настройки банков')
            ->assertViewHas('bankSettings', fn ($settings) => (bool) $settings['mbank']->accepts_rubles);

        $banks['mbank']['accepts_rubles'] = '0';
        $this->put(route('exchange-rates.bank-settings.update'), ['banks' => $banks])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('legal.exchange_rate_bank_settings', ['provider' => 'mbank', 'accepts_rubles' => false]);
    }

    public function test_non_admin_cannot_change_settings(): void
    {
        $this->actingAs(new User(['id' => 2, 'is_admin' => false, 'is_active' => true]));
        $this->put(route('exchange-rates.bank-settings.update'), ['banks' => []])->assertForbidden();
    }

    public function test_invalid_or_incomplete_settings_are_rejected_without_writes(): void
    {
        $before = DB::table('legal.exchange_rate_bank_settings')->get()->toJson();
        $this->put(route('exchange-rates.bank-settings.update'), [
            'banks' => ['mbank' => ['accepts_rubles' => 'invalid']],
        ])->assertSessionHasErrors(['banks.mbank.accepts_rubles', 'banks.obank'], null, 'bankSettings');
        $this->assertSame($before, DB::table('legal.exchange_rate_bank_settings')->get()->toJson());
    }
}
