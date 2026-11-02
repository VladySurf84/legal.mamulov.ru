<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrencySearchTest extends TestCase
{
    public function test_currency_search_matches_names_and_codes_and_supports_ajax(): void
    {
        $this->actingAs(new User(['id' => 1, 'is_admin' => true, 'is_active' => true]));
        foreach (['руб', 'dollar', '643', 'usd', 'no-such-currency-12345'] as $search) {
            $expected = DB::table('legal.currencies')
                ->whereRaw('(name_ru ILIKE ? OR name_en ILIKE ? OR currency_code::text ILIKE ? OR alpha_code ILIKE ?)', array_fill(0, 4, '%'.$search.'%'))
                ->orderBy('currency_code')->pluck('currency_code')->all();
            $this->get(route('currencies.index', ['q' => ' '.$search.' ']))->assertOk()
                ->assertViewHas('currencies', fn ($currencies) => array_column($currencies, 'currency_code') === $expected);
        }
        $this->getJson(route('currencies.index', ['q' => 'usd']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonStructure(['html', 'has_more', 'next_page'])->assertJsonPath('has_more', false);
    }
}
