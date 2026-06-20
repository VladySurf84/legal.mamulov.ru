<?php

namespace App\Http\Controllers;

use App\Models\LegalEntity;
use App\Services\Layers\BankVatLayerBuilder;
use App\Services\Layers\VatLayerBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VatLayerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'legal_id' => ['nullable', 'integer'],
            'contractor_inn' => ['nullable', 'string', 'max:12'],
            'year' => ['nullable', 'integer'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'direction' => ['nullable', 'in:input,output'],
            'source_system' => ['nullable', 'in:accountant_vat_book,bank_payment_vat'],
        ]);

        [$where, $bindings] = $this->whereClause($filters);

        $legalEntities = LegalEntity::query()
            ->orderBy('legal_name')
            ->get(['legal_id', 'legal_name', 'legal_inn']);

        $events = DB::select(<<<SQL
SELECT
    ve.*,
    l.legal_name
FROM legal.vat_events ve
JOIN legal.legal l ON l.legal_id = ve.legal_id
WHERE {$where}
ORDER BY ve.year DESC, ve.quarter DESC, ve.occurred_on DESC NULLS LAST, ve.vat_event_id DESC
LIMIT 500
SQL, $bindings);

        $summary = DB::selectOne(<<<SQL
SELECT
    COUNT(*) AS count,
    COALESCE(SUM(CASE WHEN vat_direction = 'output' THEN vat_amount ELSE 0 END), 0) AS output_vat,
    COALESCE(SUM(CASE WHEN vat_direction = 'input' THEN vat_amount ELSE 0 END), 0) AS input_vat,
    COALESCE(SUM(signed_vat_amount), 0) AS vat_balance
FROM legal.vat_events ve
WHERE {$where}
SQL, $bindings);

        return view('vat-layer.index', [
            'filters' => $filters,
            'legalEntities' => $legalEntities,
            'events' => $events,
            'summary' => [
                'count' => (int) $summary->count,
                'output_vat' => (float) $summary->output_vat,
                'input_vat' => (float) $summary->input_vat,
                'vat_balance' => (float) $summary->vat_balance,
            ],
        ]);
    }

    public function rebuild(VatLayerBuilder $builder): RedirectResponse
    {
        $count = $builder->rebuild();

        return redirect()
            ->route('vat-layer.index')
            ->with('status', "Accountant VAT layer rebuilt: {$count} event(s).");
    }

    public function rebuildBank(BankVatLayerBuilder $builder): RedirectResponse
    {
        $count = $builder->rebuild();

        return redirect()
            ->route('vat-layer.index', ['source_system' => BankVatLayerBuilder::SOURCE_SYSTEM])
            ->with('status', "Bank VAT layer rebuilt: {$count} event(s).");
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function whereClause(array $filters): array
    {
        $where = ['true'];
        $bindings = [];

        if (! empty($filters['legal_id'])) {
            $where[] = 've.legal_id = :legal_id';
            $bindings['legal_id'] = (int) $filters['legal_id'];
        }

        if (! empty($filters['contractor_inn'])) {
            $where[] = 've.contractor_inn = :contractor_inn';
            $bindings['contractor_inn'] = preg_replace('/\D+/', '', (string) $filters['contractor_inn']);
        }

        if (! empty($filters['year'])) {
            $where[] = 've.year = :year';
            $bindings['year'] = (int) $filters['year'];
        }

        if (! empty($filters['quarter'])) {
            $where[] = 've.quarter = :quarter';
            $bindings['quarter'] = (int) $filters['quarter'];
        }

        if (! empty($filters['direction'])) {
            $where[] = 've.vat_direction = :direction';
            $bindings['direction'] = $filters['direction'];
        }

        if (! empty($filters['source_system'])) {
            $where[] = 've.source_system = :source_system';
            $bindings['source_system'] = $filters['source_system'];
        }

        return [implode(' AND ', $where), $bindings];
    }
}
