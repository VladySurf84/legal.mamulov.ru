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

        [$documentWhere, $buhWhere, $bindings] = $this->whereClauses($filters);

        $counterparties = DB::select(<<<SQL
WITH document_money AS (
    {$this->documentMoneySelect()}
),
filtered_money AS (
    SELECT *
    FROM document_money
    WHERE contractor_inn IS NOT NULL
        AND contractor_inn <> ''
        AND {$documentWhere}
),
doc_agg AS (
    SELECT
        contractor_inn,
        max(contractor_name) FILTER (WHERE contractor_name IS NOT NULL AND contractor_name <> '') AS contractor_name,
        COALESCE(sum(signed_amount), 0) AS saldo,
        COALESCE(sum(income_amount), 0) AS income_amount,
        COALESCE(sum(expense_amount), 0) AS expense_amount,
        count(*) AS operations_count,
        count(DISTINCT legal_id) AS legal_entities_count
    FROM filtered_money
    GROUP BY contractor_inn
),
buh_agg AS (
    SELECT
        btrim(e.contractor_inn::text) AS contractor_inn,
        max(e.contractor_name) FILTER (WHERE e.contractor_name IS NOT NULL AND e.contractor_name <> '') AS contractor_name,
        -COALESCE(sum(e.amount_total), 0) AS buh_saldo
    FROM legal.vat_book_entries e
    JOIN legal.vat_book_imports i
        ON i.vat_book_import_id = e.vat_book_import_id
    WHERE i.is_active
        AND e.book_type = 'purchase'
        AND e.contractor_inn IS NOT NULL
        AND {$buhWhere}
    GROUP BY btrim(e.contractor_inn::text)
),
contractor_keys AS (
    SELECT contractor_inn FROM doc_agg
    UNION
    SELECT contractor_inn FROM buh_agg
)
SELECT
    ck.contractor_inn,
    COALESCE(da.contractor_name, ba.contractor_name, nullif(btrim(li.legal_name), ''), '—') AS contractor_name,
    COALESCE(da.saldo, 0) AS saldo,
    COALESCE(ba.buh_saldo, 0) AS buh_saldo,
    COALESCE(da.saldo, 0) - COALESCE(ba.buh_saldo, 0) AS saldo_diff,
    COALESCE(da.income_amount, 0) AS income_amount,
    COALESCE(da.expense_amount, 0) AS expense_amount,
    COALESCE(da.operations_count, 0) AS operations_count,
    COALESCE(da.legal_entities_count, 0) AS legal_entities_count
FROM contractor_keys ck
LEFT JOIN doc_agg da
    ON da.contractor_inn = ck.contractor_inn
LEFT JOIN buh_agg ba
    ON ba.contractor_inn = ck.contractor_inn
LEFT JOIN legal.legal_inn li
    ON btrim(li.legal_inn::text) = ck.contractor_inn
ORDER BY abs(COALESCE(da.saldo, 0) - COALESCE(ba.buh_saldo, 0)) DESC, contractor_name, ck.contractor_inn
LIMIT 500
SQL, $bindings);

        $summary = DB::selectOne(<<<SQL
WITH document_money AS (
    {$this->documentMoneySelect()}
),
filtered_money AS (
    SELECT *
    FROM document_money
    WHERE contractor_inn IS NOT NULL
        AND contractor_inn <> ''
        AND {$documentWhere}
),
doc_agg AS (
    SELECT contractor_inn, sum(signed_amount) AS saldo
    FROM filtered_money
    GROUP BY contractor_inn
),
buh_agg AS (
    SELECT
        btrim(e.contractor_inn::text) AS contractor_inn,
        -sum(e.amount_total) AS buh_saldo
    FROM legal.vat_book_entries e
    JOIN legal.vat_book_imports i
        ON i.vat_book_import_id = e.vat_book_import_id
    WHERE i.is_active
        AND e.book_type = 'purchase'
        AND e.contractor_inn IS NOT NULL
        AND {$buhWhere}
    GROUP BY btrim(e.contractor_inn::text)
),
contractor_keys AS (
    SELECT contractor_inn FROM doc_agg
    UNION
    SELECT contractor_inn FROM buh_agg
)
SELECT
    count(*) AS count,
    COALESCE(sum(COALESCE(da.saldo, 0)), 0) AS saldo,
    COALESCE(sum(COALESCE(ba.buh_saldo, 0)), 0) AS buh_saldo,
    COALESCE(sum(COALESCE(da.saldo, 0) - COALESCE(ba.buh_saldo, 0)), 0) AS saldo_diff
FROM contractor_keys ck
LEFT JOIN doc_agg da
    ON da.contractor_inn = ck.contractor_inn
LEFT JOIN buh_agg ba
    ON ba.contractor_inn = ck.contractor_inn
SQL, $bindings);

        return view('counterparties.index', [
            'counterparties' => $counterparties,
            'filters' => $filters,
            'legalEntities' => $this->legalEntities(),
            'summary' => [
                'count' => (int) $summary->count,
                'saldo' => (float) $summary->saldo,
                'buh_saldo' => (float) $summary->buh_saldo,
                'saldo_diff' => (float) $summary->saldo_diff,
            ],
        ]);
    }

    public function show(Request $request, string $contractorInn): View
    {
        $filters = $request->validate([
            'legal_id' => ['nullable', 'integer'],
        ]);

        $contractorInn = preg_replace('/\D+/', '', $contractorInn);
        abort_if($contractorInn === '', 404);

        [$documentWhere, $buhWhere, $bindings] = $this->whereClauses($filters + [
            'contractor_inn' => $contractorInn,
        ]);

        $operations = DB::select(<<<SQL
WITH document_money AS (
    {$this->documentMoneySelect()}
)
SELECT *
FROM document_money
WHERE contractor_inn IS NOT NULL
    AND contractor_inn <> ''
    AND {$documentWhere}
ORDER BY COALESCE(operation_date, document_date) DESC NULLS LAST, document_bank_transaction_id DESC
LIMIT 1000
SQL, $bindings);

        $summary = DB::selectOne(<<<SQL
WITH document_money AS (
    {$this->documentMoneySelect()}
),
doc_summary AS (
    SELECT
        count(*) AS count,
        COALESCE(sum(signed_amount), 0) AS saldo,
        COALESCE(sum(income_amount), 0) AS income_amount,
        COALESCE(sum(expense_amount), 0) AS expense_amount,
        max(contractor_name) FILTER (WHERE contractor_name IS NOT NULL AND contractor_name <> '') AS contractor_name
    FROM document_money
    WHERE contractor_inn IS NOT NULL
        AND contractor_inn <> ''
        AND {$documentWhere}
),
buh_summary AS (
    SELECT COALESCE(-sum(e.amount_total), 0) AS buh_saldo
    FROM legal.vat_book_entries e
    JOIN legal.vat_book_imports i
        ON i.vat_book_import_id = e.vat_book_import_id
    WHERE i.is_active
        AND e.book_type = 'purchase'
        AND e.contractor_inn IS NOT NULL
        AND {$buhWhere}
)
SELECT
    ds.count,
    ds.saldo,
    bs.buh_saldo,
    ds.saldo - bs.buh_saldo AS saldo_diff,
    ds.income_amount,
    ds.expense_amount,
    ds.contractor_name
FROM doc_summary ds
CROSS JOIN buh_summary bs
SQL, $bindings);

        return view('counterparties.show', [
            'contractorInn' => $contractorInn,
            'contractorName' => $summary->contractor_name ?: '—',
            'filters' => $filters,
            'legalEntities' => $this->legalEntities(),
            'operations' => $operations,
            'summary' => [
                'count' => (int) $summary->count,
                'saldo' => (float) $summary->saldo,
                'buh_saldo' => (float) $summary->buh_saldo,
                'saldo_diff' => (float) $summary->saldo_diff,
                'income_amount' => (float) $summary->income_amount,
                'expense_amount' => (float) $summary->expense_amount,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function whereClauses(array $filters): array
    {
        $documentWhere = ['true'];
        $buhWhere = ['true'];
        $bindings = [];

        if (! empty($filters['legal_id'])) {
            $documentWhere[] = 'legal_id = :legal_id';
            $buhWhere[] = 'e.legal_id = :legal_id';
            $bindings['legal_id'] = (int) $filters['legal_id'];
        }

        if (! empty($filters['contractor_inn'])) {
            $documentWhere[] = 'contractor_inn = :contractor_inn';
            $buhWhere[] = 'btrim(e.contractor_inn::text) = :contractor_inn';
            $bindings['contractor_inn'] = preg_replace('/\D+/', '', (string) $filters['contractor_inn']);
        }

        return [implode(' AND ', $documentWhere), implode(' AND ', $buhWhere), $bindings];
    }

    private function documentMoneySelect(): string
    {
        return <<<'SQL'
SELECT
    d.document_id,
    d.document_date,
    d.document_number,
    d.title AS document_title,
    ba.legal_id,
    l.legal_name,
    dbt.document_bank_transaction_id,
    dbt.operation_date,
    dbt.external_operation_id,
    dbt.account_number,
    dbt.payment_purpose,
    CASE
        WHEN btrim(dbt.account_number) = btrim(coalesce(dbt.payer_account, '')) THEN 'expense'
        ELSE 'income'
    END AS direction,
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
LEFT JOIN legal.legal l
    ON l.legal_id = ba.legal_id
SQL;
    }

    private function legalEntities()
    {
        return LegalEntity::query()
            ->orderBy('legal_name')
            ->get(['legal_id', 'legal_name', 'legal_inn']);
    }
}
