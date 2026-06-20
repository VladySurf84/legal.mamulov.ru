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

        [$where, $saldoWhere, $bindings] = $this->whereClause($filters);

        $counterparties = DB::select(<<<SQL
WITH source_rows AS (
    SELECT
        lr.legal_id,
        btrim(lr.contractor_inn::text) AS contractor_inn,
        nullif(btrim(bt.contractor_name), '') AS contractor_name,
        'legacy_bank' AS source_system
    FROM legal.legal_reconciliation lr
    LEFT JOIN legal.bank_transaction bt
        ON bt.reconciliation_type_id = lr.reconciliation_type_id
        AND bt.reconciliation_id = lr.reconciliation_id
    WHERE lr.contractor_inn IS NOT NULL

    UNION ALL

    SELECT
        e.legal_id,
        btrim(e.contractor_inn::text) AS contractor_inn,
        nullif(btrim(e.contractor_name), '') AS contractor_name,
        'vat_book' AS source_system
    FROM legal.vat_book_entries e
    JOIN legal.vat_book_imports i
        ON i.vat_book_import_id = e.vat_book_import_id
    WHERE i.is_active
        AND e.contractor_inn IS NOT NULL

    UNION ALL

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
        'bank_document' AS source_system
    FROM legal.document_bank_transaction dbt
    JOIN legal.bank_account ba
        ON ba.bank_account_id = dbt.bank_account_id

    UNION ALL

    SELECT
        lbs.legal_id,
        btrim(lbs.contractor_inn::text) AS contractor_inn,
        nullif(btrim(li.legal_name), '') AS contractor_name,
        'buh_saldo' AS source_system
    FROM legal.legal_buh_saldo lbs
    LEFT JOIN legal.legal_inn li
        ON btrim(li.legal_inn::text) = btrim(lbs.contractor_inn::text)
),
filtered_sources AS (
    SELECT *
    FROM source_rows
    WHERE contractor_inn IS NOT NULL
        AND contractor_inn <> ''
        AND {$where}
),
contractors AS (
    SELECT
        contractor_inn,
        max(contractor_name) FILTER (WHERE contractor_name IS NOT NULL AND contractor_name <> '') AS contractor_name,
        count(*) AS source_rows_count,
        count(DISTINCT legal_id) AS legal_entities_count,
        count(DISTINCT source_system) AS source_systems_count
    FROM filtered_sources
    GROUP BY contractor_inn
),
saldo AS (
    SELECT
        btrim(lbs.contractor_inn::text) AS contractor_inn,
        COALESCE(sum(lbs.saldo), 0) AS saldo
    FROM legal.legal_buh_saldo lbs
    WHERE {$saldoWhere}
    GROUP BY btrim(lbs.contractor_inn::text)
)
SELECT
    c.contractor_inn,
    COALESCE(c.contractor_name, nullif(btrim(li.legal_name), ''), '—') AS contractor_name,
    COALESCE(s.saldo, 0) AS saldo,
    c.source_rows_count,
    c.legal_entities_count,
    c.source_systems_count
FROM contractors c
LEFT JOIN saldo s
    ON s.contractor_inn = c.contractor_inn
LEFT JOIN legal.legal_inn li
    ON btrim(li.legal_inn::text) = c.contractor_inn
ORDER BY abs(COALESCE(s.saldo, 0)) DESC, contractor_name, c.contractor_inn
LIMIT 500
SQL, $bindings);

        $summary = DB::selectOne(<<<SQL
WITH source_rows AS (
    SELECT lr.legal_id, btrim(lr.contractor_inn::text) AS contractor_inn
    FROM legal.legal_reconciliation lr
    WHERE lr.contractor_inn IS NOT NULL

    UNION ALL

    SELECT e.legal_id, btrim(e.contractor_inn::text) AS contractor_inn
    FROM legal.vat_book_entries e
    JOIN legal.vat_book_imports i
        ON i.vat_book_import_id = e.vat_book_import_id
    WHERE i.is_active
        AND e.contractor_inn IS NOT NULL

    UNION ALL

    SELECT
        ba.legal_id,
        CASE
            WHEN btrim(dbt.account_number) = btrim(coalesce(dbt.payer_account, '')) THEN btrim(dbt.recipient_inn)
            ELSE btrim(dbt.payer_inn)
        END AS contractor_inn
    FROM legal.document_bank_transaction dbt
    JOIN legal.bank_account ba
        ON ba.bank_account_id = dbt.bank_account_id

    UNION ALL

    SELECT lbs.legal_id, btrim(lbs.contractor_inn::text) AS contractor_inn
    FROM legal.legal_buh_saldo lbs
),
contractors AS (
    SELECT DISTINCT contractor_inn
    FROM source_rows
    WHERE contractor_inn IS NOT NULL
        AND contractor_inn <> ''
        AND {$where}
),
saldo AS (
    SELECT
        btrim(lbs.contractor_inn::text) AS contractor_inn,
        COALESCE(sum(lbs.saldo), 0) AS saldo
    FROM legal.legal_buh_saldo lbs
    WHERE {$saldoWhere}
    GROUP BY btrim(lbs.contractor_inn::text)
)
SELECT
    count(*) AS count,
    COALESCE(sum(COALESCE(s.saldo, 0)), 0) AS saldo
FROM contractors c
LEFT JOIN saldo s
    ON s.contractor_inn = c.contractor_inn
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
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function whereClause(array $filters): array
    {
        $where = ['true'];
        $saldoWhere = ['true'];
        $bindings = [];

        if (! empty($filters['legal_id'])) {
            $where[] = 'legal_id = :legal_id';
            $saldoWhere[] = 'lbs.legal_id = :legal_id';
            $bindings['legal_id'] = (int) $filters['legal_id'];
        }

        if (! empty($filters['contractor_inn'])) {
            $contractorInn = preg_replace('/\D+/', '', (string) $filters['contractor_inn']);

            $where[] = 'contractor_inn = :contractor_inn';
            $saldoWhere[] = 'btrim(lbs.contractor_inn::text) = :contractor_inn';
            $bindings['contractor_inn'] = $contractorInn;
        }

        return [implode(' AND ', $where), implode(' AND ', $saldoWhere), $bindings];
    }
}
