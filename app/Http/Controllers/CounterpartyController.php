<?php

namespace App\Http\Controllers;

use App\Models\LegalEntity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CounterpartyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'legal_id' => ['nullable', 'integer'],
            'contractor_inn' => ['nullable', 'string', 'max:12'],
        ]);

        [$where, $bindings] = $this->whereClause($filters);

        $counterparties = DB::select(<<<SQL
WITH document_money AS (
    SELECT
        ba.legal_id,
        CASE
            WHEN btrim(dbt.account_number) = btrim(coalesce(dbt.payer_account, '')) THEN btrim(dbt.recipient_inn)
            ELSE btrim(dbt.payer_inn)
        END AS contractor_inn,
        CASE
            WHEN btrim(dbt.account_number) = btrim(coalesce(dbt.payer_account, '')) THEN nullif(btrim(dbt.recipient_name), '')
            ELSE nullif(btrim(dbt.payer_name), '')
        END AS contractor_name,
        CASE
            WHEN btrim(dbt.account_number) = btrim(coalesce(dbt.payer_account, '')) THEN -COALESCE(dbt.amount, abs(dbt.signed_amount), 0)
            ELSE COALESCE(dbt.amount, abs(dbt.signed_amount), 0)
        END AS signed_amount,
        CASE
            WHEN btrim(dbt.account_number) = btrim(coalesce(dbt.payer_account, '')) THEN 0
            ELSE COALESCE(dbt.amount, abs(dbt.signed_amount), 0)
        END AS income_amount,
        CASE
            WHEN btrim(dbt.account_number) = btrim(coalesce(dbt.payer_account, '')) THEN COALESCE(dbt.amount, abs(dbt.signed_amount), 0)
            ELSE 0
        END AS expense_amount
    FROM legal.document_bank_transaction dbt
    JOIN legal.documents d
        ON d.document_id = dbt.document_id
    JOIN legal.bank_account ba
        ON ba.bank_account_id = dbt.bank_account_id
),
filtered_money AS (
    SELECT *
    FROM document_money
    WHERE contractor_inn IS NOT NULL
        AND contractor_inn <> ''
        AND {$where}
)
SELECT
    contractor_inn,
    COALESCE(
        max(contractor_name) FILTER (WHERE contractor_name IS NOT NULL AND contractor_name <> ''),
        '—'
    ) AS contractor_name,
    COALESCE(sum(signed_amount), 0) AS saldo,
    COALESCE(sum(income_amount), 0) AS income_amount,
    COALESCE(sum(expense_amount), 0) AS expense_amount,
    count(*) AS operations_count,
    count(DISTINCT legal_id) AS legal_entities_count
FROM filtered_money
GROUP BY contractor_inn
ORDER BY abs(COALESCE(sum(signed_amount), 0)) DESC, contractor_name, contractor_inn
LIMIT 500
SQL, $bindings);

        $summary = DB::selectOne(<<<SQL
WITH document_money AS (
    SELECT
        ba.legal_id,
        CASE
            WHEN btrim(dbt.account_number) = btrim(coalesce(dbt.payer_account, '')) THEN btrim(dbt.recipient_inn)
            ELSE btrim(dbt.payer_inn)
        END AS contractor_inn,
        CASE
            WHEN btrim(dbt.account_number) = btrim(coalesce(dbt.payer_account, '')) THEN -COALESCE(dbt.amount, abs(dbt.signed_amount), 0)
            ELSE COALESCE(dbt.amount, abs(dbt.signed_amount), 0)
        END AS signed_amount
    FROM legal.document_bank_transaction dbt
    JOIN legal.documents d
        ON d.document_id = dbt.document_id
    JOIN legal.bank_account ba
        ON ba.bank_account_id = dbt.bank_account_id
),
filtered_money AS (
    SELECT *
    FROM document_money
    WHERE contractor_inn IS NOT NULL
        AND contractor_inn <> ''
        AND {$where}
),
contractors AS (
    SELECT contractor_inn, sum(signed_amount) AS saldo
    FROM filtered_money
    GROUP BY contractor_inn
)
SELECT
    count(*) AS count,
    COALESCE(sum(saldo), 0) AS saldo
FROM contractors
SQL, $bindings);

        return view('counterparties.index', [
            'counterparties' => $counterparties,
            'filters' => $filters,
            'legalEntities' => LegalEntity::query()
                ->orderBy('legal_name')
                ->get(['legal_id', 'legal_name', 'legal_inn']),
            'summary' => [
                'count' => (int) $summary->count,
                'saldo' => (float) $summary->saldo,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function whereClause(array $filters): array
    {
        $where = ['true'];
        $bindings = [];

        if (! empty($filters['legal_id'])) {
            $where[] = 'legal_id = :legal_id';
            $bindings['legal_id'] = (int) $filters['legal_id'];
        }

        if (! empty($filters['contractor_inn'])) {
            $where[] = 'contractor_inn = :contractor_inn';
            $bindings['contractor_inn'] = preg_replace('/\D+/', '', (string) $filters['contractor_inn']);
        }

        return [implode(' AND ', $where), $bindings];
    }
}
