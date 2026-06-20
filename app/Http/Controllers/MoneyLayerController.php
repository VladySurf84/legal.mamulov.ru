<?php

namespace App\Http\Controllers;

use App\Services\Layers\MoneyLayerBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MoneyLayerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'party' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        [$where, $bindings] = $this->whereClause($filters);

        $edges = DB::select(<<<SQL
SELECT
    me.*,
    dbt.account_number,
    dbt.bank_id,
    dbt.external_operation_id
FROM legal.money_edges me
JOIN legal.document_bank_transaction dbt
    ON dbt.document_bank_transaction_id = me.source_document_bank_transaction_id
WHERE {$where}
ORDER BY me.occurred_on DESC, me.money_edge_id DESC
LIMIT 500
SQL, $bindings);

        $summary = DB::selectOne(<<<SQL
SELECT
    COUNT(*) AS count,
    COALESCE(SUM(amount), 0) AS total_amount,
    MIN(occurred_on) AS min_date,
    MAX(occurred_on) AS max_date
FROM legal.money_edges me
WHERE {$where}
SQL, $bindings);

        return view('money-layer.index', [
            'filters' => $filters,
            'edges' => $edges,
            'summary' => [
                'count' => (int) $summary->count,
                'total_amount' => (float) $summary->total_amount,
                'min_date' => $summary->min_date,
                'max_date' => $summary->max_date,
            ],
        ]);
    }

    public function rebuild(MoneyLayerBuilder $builder): RedirectResponse
    {
        $count = $builder->rebuild();

        return redirect()
            ->route('money-layer.index')
            ->with('status', "Money layer rebuilt: {$count} edge(s).");
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function whereClause(array $filters): array
    {
        $where = ['true'];
        $bindings = [];

        if (! empty($filters['party'])) {
            $where[] = '(me.payer_name_snapshot ILIKE :party OR me.recipient_name_snapshot ILIKE :party OR me.payer_inn_snapshot ILIKE :party OR me.recipient_inn_snapshot ILIKE :party)';
            $bindings['party'] = '%'.$filters['party'].'%';
        }

        if (! empty($filters['date_from'])) {
            $where[] = 'me.occurred_on >= :date_from';
            $bindings['date_from'] = $filters['date_from'];
        }

        if (! empty($filters['date_to'])) {
            $where[] = 'me.occurred_on <= :date_to';
            $bindings['date_to'] = $filters['date_to'];
        }

        return [implode(' AND ', $where), $bindings];
    }
}
