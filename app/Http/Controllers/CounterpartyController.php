<?php

namespace App\Http\Controllers;

use App\Models\LegalEntity;
use App\Services\Layers\AccountantReportLinkBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CounterpartyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'legal_id' => ['nullable', 'string', 'max:12'],
            'contractor_inn' => ['nullable', 'string', 'max:12'],
            'only_negative_diff' => ['nullable', 'boolean'],
        ]);

        [$documentWhere, $buhWhere, $bindings] = $this->whereClauses($filters);
        $openingWhere = $this->openingWhereClause($filters);
        $vatWhere = $this->vatWhereClause($filters);
        $negativeDiffWhere = $this->negativeDifferenceWhere($filters);

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
        AND COALESCE(operation_date, document_date) >= COALESCE((
            SELECT max(ob.starts_on)
            FROM legal.counterparty_opening_balances ob
            WHERE ob.legal_id = document_money.legal_id
                AND btrim(ob.contractor_inn::text) = document_money.contractor_inn
        ), '-infinity'::date)
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
        AND COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date) >= COALESCE((
            SELECT max(ob.starts_on)
            FROM legal.counterparty_opening_balances ob
            WHERE ob.legal_id = e.legal_id
                AND btrim(ob.contractor_inn::text) = btrim(e.contractor_inn::text)
        ), '-infinity'::date)
    GROUP BY btrim(e.contractor_inn::text)
),
opening_agg AS (
    SELECT
        btrim(ob.contractor_inn::text) AS contractor_inn,
        COALESCE(sum(ob.amount), 0) AS opening_amount
    FROM legal.counterparty_opening_balances ob
    WHERE {$openingWhere}
    GROUP BY btrim(ob.contractor_inn::text)
),
bank_vat_agg AS (
    SELECT
        btrim(ve.contractor_inn::text) AS contractor_inn,
        COALESCE(sum(ve.signed_vat_amount), 0) AS bank_vat
    FROM legal.vat_events ve
    WHERE ve.contractor_inn IS NOT NULL
        AND btrim(ve.contractor_inn::text) <> ''
        AND ve.source_system = 'bank_payment_vat'
        AND {$vatWhere}
        AND ve.occurred_on >= COALESCE((
            SELECT max(ob.starts_on)
            FROM legal.counterparty_opening_balances ob
            WHERE ob.legal_id = ve.legal_id
                AND btrim(ob.contractor_inn::text) = btrim(ve.contractor_inn::text)
        ), '-infinity'::date)
    GROUP BY btrim(ve.contractor_inn::text)
),
accountant_vat_agg AS (
    SELECT
        btrim(e.contractor_inn::text) AS contractor_inn,
        COALESCE(sum(e.vat_amount), 0) AS accountant_vat
    FROM legal.vat_book_entries e
    JOIN legal.vat_book_imports i
        ON i.vat_book_import_id = e.vat_book_import_id
    WHERE i.is_active
        AND e.book_type = 'purchase'
        AND e.contractor_inn IS NOT NULL
        AND btrim(e.contractor_inn::text) <> ''
        AND e.vat_amount IS NOT NULL
        AND {$buhWhere}
        AND COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date) >= COALESCE((
            SELECT max(ob.starts_on)
            FROM legal.counterparty_opening_balances ob
            WHERE ob.legal_id = e.legal_id
                AND btrim(ob.contractor_inn::text) = btrim(e.contractor_inn::text)
        ), '-infinity'::date)
    GROUP BY btrim(e.contractor_inn::text)
),
vat_agg AS (
    SELECT
        COALESCE(bva.contractor_inn, ava.contractor_inn) AS contractor_inn,
        COALESCE(bva.bank_vat, 0) AS bank_vat,
        COALESCE(ava.accountant_vat, 0) AS accountant_vat
    FROM bank_vat_agg bva
    FULL JOIN accountant_vat_agg ava
        ON ava.contractor_inn = bva.contractor_inn
),
contractor_keys AS (
    SELECT contractor_inn FROM doc_agg
    UNION
    SELECT contractor_inn FROM buh_agg
    UNION
    SELECT contractor_inn FROM opening_agg
    UNION
    SELECT contractor_inn FROM vat_agg
)
SELECT
    ck.contractor_inn,
    COALESCE(da.contractor_name, ba.contractor_name, '—') AS contractor_name,
    COALESCE(da.saldo, 0) AS saldo,
    COALESCE(ba.buh_saldo, 0) AS buh_saldo,
    COALESCE(oa.opening_amount, 0) AS opening_amount,
    COALESCE(oa.opening_amount, 0) + COALESCE(da.saldo, 0) - COALESCE(ba.buh_saldo, 0) AS saldo_diff,
    COALESCE(va.bank_vat, 0) + COALESCE(va.accountant_vat, 0) AS vat_diff,
    COALESCE(da.income_amount, 0) AS income_amount,
    COALESCE(da.expense_amount, 0) AS expense_amount,
    COALESCE(da.operations_count, 0) AS operations_count,
    COALESCE(da.legal_entities_count, 0) AS legal_entities_count
FROM contractor_keys ck
LEFT JOIN doc_agg da
    ON da.contractor_inn = ck.contractor_inn
LEFT JOIN buh_agg ba
    ON ba.contractor_inn = ck.contractor_inn
LEFT JOIN opening_agg oa
    ON oa.contractor_inn = ck.contractor_inn
LEFT JOIN vat_agg va
    ON va.contractor_inn = ck.contractor_inn
WHERE {$this->excludeOwnLegalWhere($filters)}
    AND {$negativeDiffWhere}
ORDER BY abs(COALESCE(oa.opening_amount, 0) + COALESCE(da.saldo, 0) - COALESCE(ba.buh_saldo, 0)) DESC, contractor_name, ck.contractor_inn
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
        AND COALESCE(operation_date, document_date) >= COALESCE((
            SELECT max(ob.starts_on)
            FROM legal.counterparty_opening_balances ob
            WHERE ob.legal_id = document_money.legal_id
                AND btrim(ob.contractor_inn::text) = document_money.contractor_inn
        ), '-infinity'::date)
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
        AND COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date) >= COALESCE((
            SELECT max(ob.starts_on)
            FROM legal.counterparty_opening_balances ob
            WHERE ob.legal_id = e.legal_id
                AND btrim(ob.contractor_inn::text) = btrim(e.contractor_inn::text)
        ), '-infinity'::date)
    GROUP BY btrim(e.contractor_inn::text)
),
opening_agg AS (
    SELECT
        btrim(ob.contractor_inn::text) AS contractor_inn,
        sum(ob.amount) AS opening_amount
    FROM legal.counterparty_opening_balances ob
    WHERE {$openingWhere}
    GROUP BY btrim(ob.contractor_inn::text)
),
bank_vat_agg AS (
    SELECT
        btrim(ve.contractor_inn::text) AS contractor_inn,
        COALESCE(sum(ve.signed_vat_amount), 0) AS bank_vat
    FROM legal.vat_events ve
    WHERE ve.contractor_inn IS NOT NULL
        AND btrim(ve.contractor_inn::text) <> ''
        AND ve.source_system = 'bank_payment_vat'
        AND {$vatWhere}
        AND ve.occurred_on >= COALESCE((
            SELECT max(ob.starts_on)
            FROM legal.counterparty_opening_balances ob
            WHERE ob.legal_id = ve.legal_id
                AND btrim(ob.contractor_inn::text) = btrim(ve.contractor_inn::text)
        ), '-infinity'::date)
    GROUP BY btrim(ve.contractor_inn::text)
),
accountant_vat_agg AS (
    SELECT
        btrim(e.contractor_inn::text) AS contractor_inn,
        COALESCE(sum(e.vat_amount), 0) AS accountant_vat
    FROM legal.vat_book_entries e
    JOIN legal.vat_book_imports i
        ON i.vat_book_import_id = e.vat_book_import_id
    WHERE i.is_active
        AND e.book_type = 'purchase'
        AND e.contractor_inn IS NOT NULL
        AND btrim(e.contractor_inn::text) <> ''
        AND e.vat_amount IS NOT NULL
        AND {$buhWhere}
        AND COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date) >= COALESCE((
            SELECT max(ob.starts_on)
            FROM legal.counterparty_opening_balances ob
            WHERE ob.legal_id = e.legal_id
                AND btrim(ob.contractor_inn::text) = btrim(e.contractor_inn::text)
        ), '-infinity'::date)
    GROUP BY btrim(e.contractor_inn::text)
),
vat_agg AS (
    SELECT
        COALESCE(bva.contractor_inn, ava.contractor_inn) AS contractor_inn,
        COALESCE(bva.bank_vat, 0) AS bank_vat,
        COALESCE(ava.accountant_vat, 0) AS accountant_vat
    FROM bank_vat_agg bva
    FULL JOIN accountant_vat_agg ava
        ON ava.contractor_inn = bva.contractor_inn
),
contractor_keys AS (
    SELECT contractor_inn FROM doc_agg
    UNION
    SELECT contractor_inn FROM buh_agg
    UNION
    SELECT contractor_inn FROM opening_agg
    UNION
    SELECT contractor_inn FROM vat_agg
)
SELECT
    count(*) AS count,
    COALESCE(sum(COALESCE(da.saldo, 0)), 0) AS saldo,
    COALESCE(sum(COALESCE(ba.buh_saldo, 0)), 0) AS buh_saldo,
    COALESCE(sum(COALESCE(oa.opening_amount, 0)), 0) AS opening_amount,
    COALESCE(sum(COALESCE(oa.opening_amount, 0) + COALESCE(da.saldo, 0) - COALESCE(ba.buh_saldo, 0)), 0) AS saldo_diff,
    COALESCE(sum(COALESCE(va.bank_vat, 0) + COALESCE(va.accountant_vat, 0)), 0) AS vat_diff
FROM contractor_keys ck
LEFT JOIN doc_agg da
    ON da.contractor_inn = ck.contractor_inn
LEFT JOIN buh_agg ba
    ON ba.contractor_inn = ck.contractor_inn
LEFT JOIN opening_agg oa
    ON oa.contractor_inn = ck.contractor_inn
LEFT JOIN vat_agg va
    ON va.contractor_inn = ck.contractor_inn
WHERE {$this->excludeOwnLegalWhere($filters)}
    AND {$negativeDiffWhere}
SQL, $bindings);

        return view('counterparties.index', [
            'counterparties' => $counterparties,
            'filters' => $filters,
            'legalEntities' => $this->legalEntities(),
            'summary' => [
                'count' => (int) $summary->count,
                'saldo' => (float) $summary->saldo,
                'buh_saldo' => (float) $summary->buh_saldo,
                'opening_amount' => (float) $summary->opening_amount,
                'saldo_diff' => (float) $summary->saldo_diff,
                'vat_diff' => (float) $summary->vat_diff,
            ],
        ]);
    }

    public function rebuildLinks(Request $request, AccountantReportLinkBuilder $builder): RedirectResponse
    {
        $filters = $request->validate([
            'legal_id' => ['nullable', 'string', 'max:12'],
            'contractor_inn' => ['nullable', 'string', 'max:12'],
            'only_negative_diff' => ['nullable', 'boolean'],
        ]);

        $stats = $builder->rebuild([
            'legal_id' => ! empty($filters['legal_id']) ? (string) $filters['legal_id'] : null,
        ]);

        return redirect()
            ->route('counterparties.index', array_filter([
                'legal_id' => $filters['legal_id'] ?? null,
                'contractor_inn' => $filters['contractor_inn'] ?? null,
                'only_negative_diff' => ! empty($filters['only_negative_diff']) ? 1 : null,
            ], static fn ($value) => $value !== null && $value !== ''))
            ->with('status', sprintf(
                'Связи пересчитаны: кандидатов %d, однозначных %d, покупок 1 к 2: %d, продаж 1 к 2: %d, вставлено %d.',
                $stats['candidates'],
                $stats['matched'],
                $stats['purchase_pair_entries_matched'],
                $stats['sales_pair_entries_matched'],
                $stats['inserted'],
            ));
    }

    public function show(Request $request, string $contractorInn): View|JsonResponse
    {
        $filters = $request->validate([
            'legal_id' => ['nullable', 'string', 'max:12'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $page = (int) ($filters['page'] ?? 1);
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        $contractorInn = preg_replace('/\D+/', '', $contractorInn);
        abort_if($contractorInn === '', 404);

        [$documentWhere, $buhWhere, $bindings] = $this->whereClauses($filters + [
            'contractor_inn' => $contractorInn,
        ]);
        $openingWhere = $this->openingWhereClause($filters + [
            'contractor_inn' => $contractorInn,
        ]);
        $ledgerBindings = $bindings + [
            'limit' => $perPage + 1,
            'offset' => $offset,
        ];

        $ledgerEntries = DB::select(<<<SQL
WITH document_money AS (
    {$this->documentMoneySelect()}
),
opening_balances AS (
    SELECT
        ob.counterparty_opening_balance_id,
        ob.legal_id,
        l.legal_name,
        ob.starts_on,
        ob.amount,
        ob.source,
        ob.comment
    FROM legal.counterparty_opening_balances ob
    LEFT JOIN legal.legal_own l
        ON l.legal_id = ob.legal_id
    WHERE {$openingWhere}
),
opening_cutoff AS (
    SELECT max(starts_on) AS starts_on
    FROM opening_balances
),
filtered_money AS (
    SELECT *
    FROM document_money
    WHERE contractor_inn IS NOT NULL
        AND contractor_inn <> ''
        AND {$documentWhere}
        AND (
            (SELECT starts_on FROM opening_cutoff) IS NULL
            OR COALESCE(operation_date, document_date) >= (SELECT starts_on FROM opening_cutoff)
        )
),
bank_entries AS (
    SELECT
        COALESCE(operation_date, document_date) AS event_date,
        'bank' AS source_type,
        20 AS sort_order,
        document_bank_transaction_id AS source_id,
        legal_name,
        direction,
        income_amount,
        expense_amount,
        null::numeric AS purchase_amount,
        (
            SELECT abs(ve.vat_amount)
            FROM legal.vat_events ve
            WHERE ve.source_system = 'bank_payment_vat'
                AND ve.source_document_bank_transaction_id = filtered_money.document_bank_transaction_id
            LIMIT 1
        ) AS vat_amount,
        CASE
            WHEN signed_amount < 0 THEN -COALESCE((
                SELECT abs(ve.vat_amount)
                FROM legal.vat_events ve
                WHERE ve.source_system = 'bank_payment_vat'
                    AND ve.source_document_bank_transaction_id = filtered_money.document_bank_transaction_id
                LIMIT 1
            ), 0)
            ELSE COALESCE((
                SELECT abs(ve.vat_amount)
                FROM legal.vat_events ve
                WHERE ve.source_system = 'bank_payment_vat'
                    AND ve.source_document_bank_transaction_id = filtered_money.document_bank_transaction_id
                LIMIT 1
            ), 0)
        END AS vat_reconciliation_amount,
        signed_amount AS source_signed_amount,
        signed_amount AS reconciliation_amount,
        account_number AS primary_ref,
        external_operation_id AS secondary_ref,
        payment_purpose AS description,
        EXISTS (
            SELECT 1
            FROM legal.accountant_report_links arl
            WHERE arl.document_bank_transaction_id = filtered_money.document_bank_transaction_id
        ) AS is_linked
    FROM filtered_money
),
opening_entries AS (
    SELECT
        starts_on AS event_date,
        'opening_balance' AS source_type,
        10 AS sort_order,
        counterparty_opening_balance_id AS source_id,
        legal_name,
        'opening' AS direction,
        null::numeric AS income_amount,
        null::numeric AS expense_amount,
        null::numeric AS purchase_amount,
        null::numeric AS vat_amount,
        0::numeric AS vat_reconciliation_amount,
        amount AS source_signed_amount,
        amount AS reconciliation_amount,
        source AS primary_ref,
        'акт сверки' AS secondary_ref,
        comment AS description,
        false AS is_linked
    FROM opening_balances
),
purchase_entries AS (
    SELECT
        COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date) AS event_date,
        'purchase_book' AS source_type,
        30 AS sort_order,
        e.vat_book_entry_id AS source_id,
        l.legal_name,
        'purchase' AS direction,
        null::numeric AS income_amount,
        null::numeric AS expense_amount,
        e.amount_total AS purchase_amount,
        e.vat_amount,
        COALESCE(e.vat_amount, 0) AS vat_reconciliation_amount,
        -COALESCE(e.amount_total, 0) AS source_signed_amount,
        COALESCE(e.amount_total, 0) AS reconciliation_amount,
        e.invoice_number AS primary_ref,
        concat_ws(' · ',
            concat(e.year, ' Q', e.quarter),
            concat('строка ', e.row_number),
            nullif(e.operation_code, '')
        ) AS secondary_ref,
        concat_ws(' · ',
            nullif(e.contractor_name, ''),
            CASE WHEN e.acceptance_date IS NOT NULL THEN concat('принят ', to_char(e.acceptance_date, 'DD.MM.YYYY')) END,
            CASE WHEN e.payment_doc_number IS NOT NULL OR e.payment_doc_date IS NOT NULL
                THEN concat_ws(' ', nullif(e.payment_doc_number, ''), to_char(e.payment_doc_date, 'DD.MM.YYYY'))
            END
        ) AS description,
        EXISTS (
            SELECT 1
            FROM legal.accountant_report_links arl
            WHERE arl.vat_book_entry_id = e.vat_book_entry_id
        ) AS is_linked
    FROM legal.vat_book_entries e
    JOIN legal.vat_book_imports i
        ON i.vat_book_import_id = e.vat_book_import_id
    LEFT JOIN legal.legal_own l
        ON l.legal_id = e.legal_id
    WHERE i.is_active
        AND e.book_type = 'purchase'
        AND e.contractor_inn IS NOT NULL
        AND {$buhWhere}
        AND (
            (SELECT starts_on FROM opening_cutoff) IS NULL
            OR COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date) >= (SELECT starts_on FROM opening_cutoff)
        )
),
ledger_entries AS (
    SELECT * FROM opening_entries
    UNION ALL
    SELECT * FROM bank_entries
    UNION ALL
    SELECT * FROM purchase_entries
),
numbered_ledger_entries AS (
    SELECT
        *,
        sum(reconciliation_amount) OVER (
            ORDER BY event_date, sort_order, source_id
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ) AS running_saldo,
        sum(vat_reconciliation_amount) OVER (
            ORDER BY event_date, sort_order, source_id
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ) AS running_vat_saldo
    FROM ledger_entries
)
SELECT *
FROM numbered_ledger_entries
ORDER BY event_date DESC NULLS LAST, sort_order DESC, source_id DESC
LIMIT :limit OFFSET :offset
SQL, $ledgerBindings);

        $hasMoreLedgerEntries = count($ledgerEntries) > $perPage;
        if ($hasMoreLedgerEntries) {
            array_pop($ledgerEntries);
        }

        $ledgerPagination = [
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => $hasMoreLedgerEntries,
        ];

        if ($request->ajax()) {
            return response()->json([
                'html' => view('counterparties.partials.ledger-rows', [
                    'contractorInn' => $contractorInn,
                    'filters' => $filters,
                    'ledgerEntries' => $ledgerEntries,
                    'ledgerPagination' => $ledgerPagination,
                ])->render(),
                'next_page' => $hasMoreLedgerEntries ? $page + 1 : null,
                'has_more' => $hasMoreLedgerEntries,
            ]);
        }

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
        AND (
            SELECT max(ob.starts_on) IS NULL
                OR COALESCE(document_money.operation_date, document_money.document_date) >= max(ob.starts_on)
            FROM legal.counterparty_opening_balances ob
            WHERE {$openingWhere}
        )
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
        AND (
            SELECT max(ob.starts_on) IS NULL
                OR COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date) >= max(ob.starts_on)
            FROM legal.counterparty_opening_balances ob
            WHERE {$openingWhere}
        )
),
opening_summary AS (
    SELECT COALESCE(sum(ob.amount), 0) AS opening_amount
    FROM legal.counterparty_opening_balances ob
    WHERE {$openingWhere}
)
SELECT
    ds.count,
    ds.saldo,
    bs.buh_saldo,
    os.opening_amount,
    os.opening_amount + ds.saldo - bs.buh_saldo AS saldo_diff,
    ds.income_amount,
    ds.expense_amount,
    ds.contractor_name
FROM doc_summary ds
CROSS JOIN buh_summary bs
CROSS JOIN opening_summary os
SQL, $bindings);

        return view('counterparties.show', [
            'contractorInn' => $contractorInn,
            'contractorName' => $summary->contractor_name ?: '—',
            'filters' => $filters,
            'legalEntities' => $this->legalEntities(),
            'ledgerEntries' => $ledgerEntries,
            'ledgerPagination' => $ledgerPagination,
            'nextPage' => $hasMoreLedgerEntries ? $page + 1 : null,
            'summary' => [
                'count' => (int) $summary->count,
                'saldo' => (float) $summary->saldo,
                'buh_saldo' => (float) $summary->buh_saldo,
                'opening_amount' => (float) $summary->opening_amount,
                'saldo_diff' => (float) $summary->saldo_diff,
                'income_amount' => (float) $summary->income_amount,
                'expense_amount' => (float) $summary->expense_amount,
            ],
        ]);
    }

    public function storeOpeningBalance(Request $request, string $contractorInn): RedirectResponse
    {
        $contractorInn = preg_replace('/\D+/', '', $contractorInn);
        abort_if($contractorInn === '', 404);

        $validated = $request->validate([
            'legal_id' => ['required', 'string', 'max:12'],
            'starts_on' => ['required', 'date'],
            'amount' => ['required', 'numeric'],
            'source' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);

        DB::table('legal.counterparty_opening_balances')->updateOrInsert(
            [
                'legal_id' => (string) $validated['legal_id'],
                'contractor_inn' => $contractorInn,
                'starts_on' => $validated['starts_on'],
            ],
            [
                'amount' => $validated['amount'],
                'source' => $validated['source'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'updated_at' => now(),
            ],
        );

        return redirect()
            ->route('counterparties.show', [
                'contractorInn' => $contractorInn,
                'legal_id' => (string) $validated['legal_id'],
            ])
            ->with('status', 'Входящее сальдо сохранено.');
    }

    public function destroyOpeningBalance(Request $request, string $contractorInn, int $openingBalanceId): RedirectResponse
    {
        $contractorInn = preg_replace('/\D+/', '', $contractorInn);
        abort_if($contractorInn === '', 404);

        $openingBalance = DB::table('legal.counterparty_opening_balances')
            ->where('counterparty_opening_balance_id', $openingBalanceId)
            ->where('contractor_inn', $contractorInn)
            ->first(['legal_id']);

        abort_if($openingBalance === null, 404);

        DB::table('legal.counterparty_opening_balances')
            ->where('counterparty_opening_balance_id', $openingBalanceId)
            ->delete();

        return redirect()
            ->route('counterparties.show', [
                'contractorInn' => $contractorInn,
                'legal_id' => $request->input('legal_id', $openingBalance->legal_id),
                'page' => $request->input('page', 1),
            ])
            ->with('status', 'Входящее сальдо удалено.');
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
            $bindings['legal_id'] = (string) $filters['legal_id'];
        }

        if (! empty($filters['contractor_inn'])) {
            $documentWhere[] = 'contractor_inn = :contractor_inn';
            $buhWhere[] = 'btrim(e.contractor_inn::text) = :contractor_inn';
            $bindings['contractor_inn'] = preg_replace('/\D+/', '', (string) $filters['contractor_inn']);
        }

        return [implode(' AND ', $documentWhere), implode(' AND ', $buhWhere), $bindings];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function openingWhereClause(array $filters): string
    {
        $where = ['true'];

        if (! empty($filters['legal_id'])) {
            $where[] = 'ob.legal_id = :legal_id';
        }

        if (! empty($filters['contractor_inn'])) {
            $where[] = 'btrim(ob.contractor_inn::text) = :contractor_inn';
        }

        return implode(' AND ', $where);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function vatWhereClause(array $filters): string
    {
        $where = ['true'];

        if (! empty($filters['legal_id'])) {
            $where[] = 've.legal_id = :legal_id';
        }

        if (! empty($filters['contractor_inn'])) {
            $where[] = 'btrim(ve.contractor_inn::text) = :contractor_inn';
        }

        return implode(' AND ', $where);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function excludeOwnLegalWhere(array $filters): string
    {
        if (empty($filters['legal_id'])) {
            return 'true';
        }

        return <<<'SQL'
NOT EXISTS (
    SELECT 1
    FROM legal.legal_own own_legal
    WHERE own_legal.legal_id = :legal_id
        AND own_legal.legal_inn IS NOT NULL
        AND btrim(own_legal.legal_inn::text) = ck.contractor_inn
)
SQL;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function negativeDifferenceWhere(array $filters): string
    {
        if (empty($filters['only_negative_diff'])) {
            return 'true';
        }

        return '(COALESCE(oa.opening_amount, 0) + COALESCE(da.saldo, 0) - COALESCE(ba.buh_saldo, 0)) < 0';
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
LEFT JOIN legal.legal_own l
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
