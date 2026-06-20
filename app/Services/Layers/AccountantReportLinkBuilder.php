<?php

namespace App\Services\Layers;

use Illuminate\Support\Facades\DB;

class AccountantReportLinkBuilder
{
    public const ALGORITHM = 'accountant_report_match_v1';

    /**
     * @param array{legal_id?: int|null, year?: int|null, quarter?: int|null} $filters
     * @return array{candidates: int, entries_with_candidates: int, ambiguous_entries: int, ambiguous_transactions: int, matched: int, inserted: int}
     */
    public function rebuild(array $filters = [], bool $dryRun = false): array
    {
        return DB::transaction(function () use ($filters, $dryRun): array {
            $stats = $this->stats($filters);

            if ($dryRun) {
                return $stats + ['inserted' => 0];
            }

            $this->deleteAlgorithmLinks($filters);

            DB::insert($this->insertSql($filters), [
                ...$this->bindings($filters),
                self::ALGORITHM,
            ]);

            return $stats + [
                'inserted' => $this->insertedCount($filters),
            ];
        });
    }

    /**
     * @param array{legal_id?: int|null, year?: int|null, quarter?: int|null} $filters
     * @return array{candidates: int, entries_with_candidates: int, ambiguous_entries: int, ambiguous_transactions: int, matched: int}
     */
    private function stats(array $filters): array
    {
        $row = DB::selectOne(<<<SQL
WITH candidates AS (
    {$this->candidatesSql($filters)}
),
counted_candidates AS (
    SELECT
        *,
        count(*) OVER (PARTITION BY vat_book_entry_id) AS entry_candidate_count,
        count(*) OVER (PARTITION BY document_bank_transaction_id) AS transaction_candidate_count
    FROM candidates
),
matched_candidates AS (
    SELECT *
    FROM counted_candidates c
    WHERE entry_candidate_count = 1
      AND transaction_candidate_count = 1
      AND NOT EXISTS (
          SELECT 1
          FROM legal.accountant_report_links existing
          WHERE existing.source <> 'algorithm'
            AND (
                existing.vat_book_entry_id = c.vat_book_entry_id
                OR existing.document_bank_transaction_id = c.document_bank_transaction_id
            )
      )
)
SELECT
    count(*) AS candidates,
    count(DISTINCT vat_book_entry_id) AS entries_with_candidates,
    count(DISTINCT vat_book_entry_id) FILTER (WHERE entry_candidate_count > 1) AS ambiguous_entries,
    count(DISTINCT document_bank_transaction_id) FILTER (WHERE transaction_candidate_count > 1) AS ambiguous_transactions,
    (SELECT count(*) FROM matched_candidates) AS matched
FROM counted_candidates
SQL, $this->bindings($filters));

        return [
            'candidates' => (int) ($row->candidates ?? 0),
            'entries_with_candidates' => (int) ($row->entries_with_candidates ?? 0),
            'ambiguous_entries' => (int) ($row->ambiguous_entries ?? 0),
            'ambiguous_transactions' => (int) ($row->ambiguous_transactions ?? 0),
            'matched' => (int) ($row->matched ?? 0),
        ];
    }

    /**
     * @param array{legal_id?: int|null, year?: int|null, quarter?: int|null} $filters
     */
    private function deleteAlgorithmLinks(array $filters): void
    {
        DB::delete(<<<SQL
DELETE FROM legal.accountant_report_links links
USING legal.vat_book_entries e
WHERE links.vat_book_entry_id = e.vat_book_entry_id
  AND links.source = 'algorithm'
  AND links.algorithm = ?
  {$this->entryFilterSql($filters, 'e')}
SQL, $this->bindings($filters, [self::ALGORITHM]));
    }

    /**
     * @param array{legal_id?: int|null, year?: int|null, quarter?: int|null} $filters
     */
    private function insertedCount(array $filters): int
    {
        return (int) DB::table('legal.accountant_report_links as links')
            ->join('legal.vat_book_entries as e', 'e.vat_book_entry_id', '=', 'links.vat_book_entry_id')
            ->where('links.source', 'algorithm')
            ->where('links.algorithm', self::ALGORITHM)
            ->when($filters['legal_id'] ?? null, fn ($query, $legalId) => $query->where('e.legal_id', (int) $legalId))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->where('e.year', (int) $year))
            ->when($filters['quarter'] ?? null, fn ($query, $quarter) => $query->where('e.quarter', (int) $quarter))
            ->count();
    }

    /**
     * @param array{legal_id?: int|null, year?: int|null, quarter?: int|null} $filters
     */
    private function insertSql(array $filters): string
    {
        return <<<SQL
WITH candidates AS (
    {$this->candidatesSql($filters)}
),
counted_candidates AS (
    SELECT
        *,
        count(*) OVER (PARTITION BY vat_book_entry_id) AS entry_candidate_count,
        count(*) OVER (PARTITION BY document_bank_transaction_id) AS transaction_candidate_count
    FROM candidates
),
matched_candidates AS (
    SELECT *
    FROM counted_candidates c
    WHERE entry_candidate_count = 1
      AND transaction_candidate_count = 1
      AND NOT EXISTS (
          SELECT 1
          FROM legal.accountant_report_links existing
          WHERE existing.source <> 'algorithm'
            AND (
                existing.vat_book_entry_id = c.vat_book_entry_id
                OR existing.document_bank_transaction_id = c.document_bank_transaction_id
            )
      )
)
INSERT INTO legal.accountant_report_links (
    accountant_report_link_type_id,
    vat_book_entry_id,
    document_bank_transaction_id,
    amount,
    vat_amount,
    currency,
    matched_on,
    confidence,
    source,
    algorithm,
    metadata,
    created_at,
    updated_at
)
SELECT
    link_type.accountant_report_link_type_id,
    m.vat_book_entry_id,
    m.document_bank_transaction_id,
    m.amount,
    m.vat_amount,
    m.currency,
    current_date,
    0.9500,
    'algorithm',
    ?,
    jsonb_build_object(
        'rule', 'exact_purchase_payment_before_upd',
        'vat_book_date', m.vat_book_date,
        'bank_operation_date', m.bank_operation_date,
        'contractor_inn', m.contractor_inn,
        'bank_amount', m.bank_amount
    ),
    now(),
    now()
FROM matched_candidates m
CROSS JOIN legal.accountant_report_link_types link_type
WHERE link_type.alias = 'payment_closes_vat_book_entry'
SQL;
    }

    /**
     * @param array{legal_id?: int|null, year?: int|null, quarter?: int|null} $filters
     */
    private function candidatesSql(array $filters): string
    {
        return <<<SQL
SELECT
    e.vat_book_entry_id,
    dbt.document_bank_transaction_id,
    e.legal_id,
    btrim(e.contractor_inn::text) AS contractor_inn,
    COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date) AS vat_book_date,
    dbt.operation_date AS bank_operation_date,
    e.amount_total AS amount,
    e.vat_amount,
    COALESCE(dbt.currency, 'RUB') AS currency,
    ABS(COALESCE(dbt.signed_amount, dbt.amount, 0)) AS bank_amount
FROM legal.vat_book_entries e
JOIN legal.vat_book_imports i
    ON i.vat_book_import_id = e.vat_book_import_id
JOIN legal.document_bank_transaction dbt
    ON btrim(dbt.recipient_inn::text) = btrim(e.contractor_inn::text)
JOIN legal.bank_account ba
    ON ba.bank_account_id = dbt.bank_account_id
    AND ba.legal_id = e.legal_id
WHERE i.is_active
  AND e.book_type = 'purchase'
  AND e.amount_total IS NOT NULL
  AND e.amount_total >= 0
  AND e.contractor_inn IS NOT NULL
  AND btrim(e.contractor_inn::text) <> ''
  AND COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date) IS NOT NULL
  AND dbt.operation_date IS NOT NULL
  AND dbt.signed_amount < 0
  AND ABS(COALESCE(dbt.signed_amount, dbt.amount, 0)) = e.amount_total
  AND dbt.operation_date < COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date)
  {$this->entryFilterSql($filters, 'e')}
SQL;
    }

    /**
     * @param array{legal_id?: int|null, year?: int|null, quarter?: int|null} $filters
     */
    private function entryFilterSql(array $filters, string $alias): string
    {
        $sql = '';

        if (isset($filters['legal_id']) && $filters['legal_id'] !== null) {
            $sql .= " AND {$alias}.legal_id = ?";
        }

        if (isset($filters['year']) && $filters['year'] !== null) {
            $sql .= " AND {$alias}.year = ?";
        }

        if (isset($filters['quarter']) && $filters['quarter'] !== null) {
            $sql .= " AND {$alias}.quarter = ?";
        }

        return $sql;
    }

    /**
     * @param array{legal_id?: int|null, year?: int|null, quarter?: int|null} $filters
     * @param list<mixed> $prefix
     * @return list<mixed>
     */
    private function bindings(array $filters, array $prefix = []): array
    {
        $bindings = $prefix;

        if (isset($filters['legal_id']) && $filters['legal_id'] !== null) {
            $bindings[] = (int) $filters['legal_id'];
        }

        if (isset($filters['year']) && $filters['year'] !== null) {
            $bindings[] = (int) $filters['year'];
        }

        if (isset($filters['quarter']) && $filters['quarter'] !== null) {
            $bindings[] = (int) $filters['quarter'];
        }

        return $bindings;
    }
}
