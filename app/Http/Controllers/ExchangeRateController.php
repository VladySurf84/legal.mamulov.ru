<?php

namespace App\Http\Controllers;

use App\Services\ExchangeRates\KyrgyzBankExchangeRateSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExchangeRateController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'provider' => ['nullable', 'string', 'max:50'],
            'rate_type' => ['nullable', 'string', 'max:50'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'show_history' => ['nullable', 'boolean'],
        ]);

        $query = DB::table('legal.exchange_rates as rates')
            ->leftJoin('legal.currencies as currency', 'currency.alpha_code', '=', 'rates.currency_code')
            ->leftJoin('legal.source_records as first_source', 'first_source.source_record_id', '=', 'rates.first_source_record_id')
            ->leftJoin('legal.source_records as last_source', 'last_source.source_record_id', '=', 'rates.last_source_record_id');

        if (empty($filters['show_history'])) {
            $query->whereNull('rates.observed_to');
        }

        if (! empty($filters['provider'])) {
            $query->where('rates.provider', $filters['provider']);
        }

        if (! empty($filters['rate_type'])) {
            $query->where('rates.rate_type', $filters['rate_type']);
        }

        if (! empty($filters['currency_code'])) {
            $query->where('rates.currency_code', strtoupper($filters['currency_code']));
        }

        $rates = $query
            ->orderBy('rates.provider')
            ->orderBy('rates.rate_type')
            ->orderBy('rates.currency_code')
            ->orderByDesc('rates.observed_from')
            ->limit(500)
            ->get([
                'rates.exchange_rate_id',
                'rates.provider',
                'rates.rate_type',
                'rates.currency_code',
                'rates.rate_currency_code',
                'rates.buy_rate',
                'rates.sell_rate',
                'rates.official_rate',
                'rates.bank_valid_from',
                'rates.observed_from',
                'rates.observed_to',
                'rates.first_seen_at',
                'rates.last_seen_at',
                'rates.quote_hash',
                'currency.name_ru as currency_name',
                'first_source.source_record_id as first_source_record_id',
                'last_source.source_record_id as last_source_record_id',
            ]);

        return view('exchange-rates.index', [
            'rates' => $rates,
            'filters' => $filters,
            'providers' => $this->distinct('provider'),
            'rateTypes' => $this->distinct('rate_type'),
            'currencyCodes' => $this->distinct('currency_code'),
        ]);
    }

    public function sync(KyrgyzBankExchangeRateSyncService $service): RedirectResponse
    {
        $summary = $service->sync(['mbank', 'obank'], [
            'started_by_type' => 'user',
            'started_by_user_id' => auth()->id(),
            'started_from' => 'ui',
        ]);

        return redirect()
            ->route('exchange-rates.index')
            ->with('status', sprintf(
                'Курсы обновлены: запуск #%d, провайдеров %d, курсов %d, открыто %d, продлено %d, закрыто %d.',
                $summary['sync_run_id'],
                $summary['providers'],
                $summary['quotes'],
                $summary['intervals_opened'],
                $summary['intervals_updated'],
                $summary['intervals_closed'],
            ));
    }

    /**
     * @return array<string, string>
     */
    private function distinct(string $column): array
    {
        return DB::table('legal.exchange_rates')
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->map(fn ($value) => (string) $value)
            ->all();
    }
}
