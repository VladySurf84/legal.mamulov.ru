<?php

namespace App\Services\Layers;

use Illuminate\Support\Facades\DB;

class AccountantReportLinkBuilder
{
    public const ALGORITHM = 'accountant_report_match_v1';

    /**
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
     * @return array{candidates: int, entries_with_candidates: int, ambiguous_entries: int, ambiguous_transactions: int, matched: int, purchase_pair_entries_matched: int, purchase_pair_links_inserted: int, sales_pair_entries_matched: int, sales_pair_links_inserted: int, inserted: int}
     */
    public function rebuild(array $filters = [], bool $dryRun = false): array
    {
        return DB::transaction(function () use ($filters, $dryRun): array {
            $stats = $this->stats($filters);

            if ($dryRun) {
                return $stats + $this->pairStats($filters) + ['inserted' => 0];
            }

            $this->deleteAlgorithmLinks($filters);

            DB::insert($this->insertSql($filters), [
                ...$this->bindings($filters),
                self::ALGORITHM,
            ]);

            $purchasePairLinksInserted = $this->insertPurchasePairLinks($filters);
            $salesPairLinksInserted = $this->insertSalesPairLinks($filters);

            return $stats + [
                'purchase_pair_entries_matched' => intdiv($purchasePairLinksInserted, 2),
                'purchase_pair_links_inserted' => $purchasePairLinksInserted,
                'sales_pair_entries_matched' => intdiv($salesPairLinksInserted, 2),
                'sales_pair_links_inserted' => $salesPairLinksInserted,
                'inserted' => $this->insertedCount($filters),
            ];
        });
    }

    /**
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
     * @return array{candidates: int, entries_with_candidates: int, ambiguous_entries: int, ambiguous_transactions: int, matched: int}
     */
    private function stats(array $filters): array
    {
        $row = DB::selectOne(<<<SQL
WITH candidates AS (
    {$this->candidatesSql($filters)}
),
ranked_candidates AS (
    SELECT
        *,
        count(*) OVER (PARTITION BY document_bank_transaction_id) AS transaction_candidate_count,
        row_number() OVER (
            PARTITION BY document_bank_transaction_id
            ORDER BY vat_book_date - bank_operation_date, vat_book_date, vat_book_entry_id
        ) AS transaction_choice_rank
    FROM candidates
),
transaction_best_candidates AS (
    SELECT *
    FROM ranked_candidates
    WHERE transaction_choice_rank = 1
),
counted_candidates AS (
    SELECT
        *,
        count(*) OVER (PARTITION BY vat_book_entry_id) AS entry_candidate_count
    FROM transaction_best_candidates
),
matched_candidates AS (
    SELECT *
    FROM counted_candidates c
    WHERE entry_candidate_count = 1
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
    (SELECT count(*) FROM candidates) AS candidates,
    (SELECT count(DISTINCT vat_book_entry_id) FROM candidates) AS entries_with_candidates,
    count(DISTINCT vat_book_entry_id) FILTER (WHERE entry_candidate_count > 1) AS ambiguous_entries,
    (SELECT count(DISTINCT document_bank_transaction_id) FROM ranked_candidates WHERE transaction_candidate_count > 1) AS ambiguous_transactions,
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
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
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
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
     */
    private function insertedCount(array $filters): int
    {
        return (int) DB::table('legal.accountant_report_links as links')
            ->join('legal.vat_book_entries as e', 'e.vat_book_entry_id', '=', 'links.vat_book_entry_id')
            ->where('links.source', 'algorithm')
            ->where('links.algorithm', self::ALGORITHM)
            ->when($filters['legal_id'] ?? null, fn ($query, $legalId) => $query->where('e.legal_id', (string) $legalId))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->where('e.year', (int) $year))
            ->when($filters['quarter'] ?? null, fn ($query, $quarter) => $query->where('e.quarter', (int) $quarter))
            ->count();
    }

    /**
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
     * @return array{purchase_pair_entries_matched: int, purchase_pair_links_inserted: int, sales_pair_entries_matched: int, sales_pair_links_inserted: int}
     */
    private function pairStats(array $filters): array
    {
        return [
            'purchase_pair_entries_matched' => 0,
            'purchase_pair_links_inserted' => 0,
            'sales_pair_entries_matched' => 0,
            'sales_pair_links_inserted' => 0,
        ];
    }

    /**
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
     */
    private function insertPurchasePairLinks(array $filters): int
    {
        $linkTypeId = DB::table('legal.accountant_report_link_types')
            ->where('alias', 'payment_closes_vat_book_entry')
            ->value('accountant_report_link_type_id');

        if ($linkTypeId === null) {
            return 0;
        }

        $inserted = 0;

        foreach ($this->purchaseBookEntries($filters) as $entry) {
            $pair = $this->purchaseBankTransactionPair($entry);

            if (count($pair) !== 2) {
                continue;
            }

            $inserted += $this->insertPairLinks(
                (int) $linkTypeId,
                $entry,
                $pair,
                'purchase_book_entry_closed_by_two_bank_payments',
            );
        }

        return $inserted;
    }

    /**
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
     */
    private function insertSalesPairLinks(array $filters): int
    {
        $linkTypeId = DB::table('legal.accountant_report_link_types')
            ->where('alias', 'payment_closes_vat_book_entry')
            ->value('accountant_report_link_type_id');

        if ($linkTypeId === null) {
            return 0;
        }

        $inserted = 0;

        foreach ($this->salesBookEntries($filters) as $entry) {
            $pair = $this->salesBankTransactionPair($entry);

            if (count($pair) !== 2) {
                continue;
            }

            $inserted += $this->insertPairLinks(
                (int) $linkTypeId,
                $entry,
                $pair,
                'sales_book_entry_closed_by_two_bank_payments',
            );
        }

        return $inserted;
    }

    /**
     * @param list<object> $pair
     */
    private function insertPairLinks(int $linkTypeId, object $entry, array $pair, string $rule): int
    {
        $amountTotal = (float) $entry->amount_total;
        $vatTotal = $entry->vat_amount !== null ? (float) $entry->vat_amount : null;
        $firstAmount = (float) $pair[0]->bank_amount;
        $firstVat = $vatTotal !== null && $amountTotal > 0
            ? round($vatTotal * ($firstAmount / $amountTotal), 2)
            : null;
        $inserted = 0;

        foreach ($pair as $index => $transaction) {
            $amount = (float) $transaction->bank_amount;
            $vatAmount = null;

            if ($vatTotal !== null) {
                $vatAmount = $index === 0
                    ? $firstVat
                    : round($vatTotal - (float) $firstVat, 2);
            }

            DB::table('legal.accountant_report_links')->insert([
                'accountant_report_link_type_id' => $linkTypeId,
                'vat_book_entry_id' => (int) $entry->vat_book_entry_id,
                'document_bank_transaction_id' => (int) $transaction->document_bank_transaction_id,
                'amount' => $amount,
                'vat_amount' => $vatAmount,
                'currency' => $transaction->currency ?? '643',
                'matched_on' => now()->toDateString(),
                'confidence' => 0.9000,
                'source' => 'algorithm',
                'algorithm' => self::ALGORITHM,
                'metadata' => json_encode([
                    'rule' => $rule,
                    'vat_book_date' => $entry->vat_book_date,
                    'bank_operation_date' => $transaction->bank_operation_date,
                    'contractor_inn' => $entry->contractor_inn,
                    'vat_book_amount' => $entry->amount_total,
                    'pair_total' => $entry->amount_total,
                    'payment_index' => $index + 1,
                    'payment_count' => 2,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inserted++;
        }

        return $inserted;
    }

    /**
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
     * @return list<object>
     */
    private function purchaseBookEntries(array $filters): array
    {
        return $this->pairBookEntries($filters, 'purchase');
    }

    /**
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
     * @return list<object>
     */
    private function salesBookEntries(array $filters): array
    {
        return $this->pairBookEntries($filters, 'sales');
    }

    /**
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
     * @return list<object>
     */
    private function pairBookEntries(array $filters, string $bookType): array
    {
        return DB::select(<<<SQL
SELECT
    e.vat_book_entry_id,
    e.legal_id,
    btrim(e.contractor_inn::text) AS contractor_inn,
    COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date) AS vat_book_date,
    e.amount_total,
    e.vat_amount
FROM legal.vat_book_entries e
JOIN legal.vat_book_imports i
    ON i.vat_book_import_id = e.vat_book_import_id
WHERE i.is_active
  AND e.book_type = ?
  AND e.amount_total IS NOT NULL
  AND e.amount_total > 0
  AND e.contractor_inn IS NOT NULL
  AND btrim(e.contractor_inn::text) <> ''
  AND COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date) IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM legal.accountant_report_links existing
      WHERE existing.vat_book_entry_id = e.vat_book_entry_id
  )
  {$this->entryFilterSql($filters, 'e')}
ORDER BY COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date), e.vat_book_entry_id
SQL, $this->bindings($filters, [$bookType]));
    }

    /**
     * @return list<object>
     */
    private function purchaseBankTransactionPair(object $entry): array
    {
        return $this->bankTransactionPair(
            $entry,
            'btrim(dbt.recipient_inn::text) = ?',
            'btrim(dbt.account_number::text) = btrim(dbt.payer_account::text)',
        );
    }

    /**
     * @return list<object>
     */
    private function salesBankTransactionPair(object $entry): array
    {
        return $this->bankTransactionPair(
            $entry,
            'btrim(dbt.payer_inn::text) = ?',
            'btrim(dbt.account_number::text) = btrim(dbt.recipient_account::text)',
        );
    }

    /**
     * @return list<object>
     */
    private function bankTransactionPair(object $entry, string $contractorInnWhere, string $directionWhere): array
    {
        return DB::select(<<<SQL
WITH available_transactions AS (
    SELECT
        dbt.document_bank_transaction_id,
        dbt.operation_date AS bank_operation_date,
        ABS(COALESCE(dbt.amount, dbt.signed_amount, 0)) AS bank_amount,
        COALESCE(dbt_currency.currency_code, dbt.currency, '643') AS currency
    FROM legal.document_bank_transaction dbt
    JOIN legal.bank_account ba
        ON ba.bank_account_id = dbt.bank_account_id
        AND ba.legal_id = ?
    LEFT JOIN legal.currency_aliases dbt_currency
        ON dbt.currency IS NOT NULL
        AND upper(btrim(dbt.currency::text)) = dbt_currency.currency_alias
    WHERE dbt.operation_date IS NOT NULL
      AND {$contractorInnWhere}
      AND {$directionWhere}
      AND dbt.operation_date <= ?::date
      AND ABS(COALESCE(dbt.amount, dbt.signed_amount, 0)) > 0
      AND NOT EXISTS (
          SELECT 1
          FROM legal.accountant_report_links existing
          WHERE existing.document_bank_transaction_id = dbt.document_bank_transaction_id
      )
),
matching_pairs AS (
    SELECT
        first_payment.document_bank_transaction_id AS first_document_bank_transaction_id,
        second_payment.document_bank_transaction_id AS second_document_bank_transaction_id,
        first_payment.bank_operation_date AS first_bank_operation_date,
        second_payment.bank_operation_date AS second_bank_operation_date,
        first_payment.bank_amount AS first_bank_amount,
        second_payment.bank_amount AS second_bank_amount,
        first_payment.currency AS first_currency,
        second_payment.currency AS second_currency,
        GREATEST(
            ABS(first_payment.bank_operation_date - ?::date),
            ABS(second_payment.bank_operation_date - ?::date)
        ) AS max_date_gap
    FROM available_transactions first_payment
    JOIN available_transactions second_payment
        ON first_payment.document_bank_transaction_id < second_payment.document_bank_transaction_id
        AND first_payment.currency = second_payment.currency
    WHERE round(first_payment.bank_amount + second_payment.bank_amount, 2) = round(?::numeric, 2)
    ORDER BY
        max_date_gap,
        LEAST(first_payment.bank_operation_date, second_payment.bank_operation_date),
        GREATEST(first_payment.bank_operation_date, second_payment.bank_operation_date),
        first_payment.document_bank_transaction_id,
        second_payment.document_bank_transaction_id
    LIMIT 1
)
SELECT
    first_document_bank_transaction_id AS document_bank_transaction_id,
    first_bank_operation_date AS bank_operation_date,
    first_bank_amount AS bank_amount,
    first_currency AS currency
FROM matching_pairs
UNION ALL
SELECT
    second_document_bank_transaction_id AS document_bank_transaction_id,
    second_bank_operation_date AS bank_operation_date,
    second_bank_amount AS bank_amount,
    second_currency AS currency
FROM matching_pairs
ORDER BY bank_operation_date, document_bank_transaction_id
SQL, [
            (string) $entry->legal_id,
            (string) $entry->contractor_inn,
            $entry->vat_book_date,
            $entry->vat_book_date,
            $entry->vat_book_date,
            $entry->amount_total,
        ]);
    }

    /**
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
     */
    private function insertSql(array $filters): string
    {
        return <<<SQL
WITH candidates AS (
    {$this->candidatesSql($filters)}
),
ranked_candidates AS (
    SELECT
        *,
        count(*) OVER (PARTITION BY document_bank_transaction_id) AS transaction_candidate_count,
        row_number() OVER (
            PARTITION BY document_bank_transaction_id
            ORDER BY vat_book_date - bank_operation_date, vat_book_date, vat_book_entry_id
        ) AS transaction_choice_rank
    FROM candidates
),
transaction_best_candidates AS (
    SELECT *
    FROM ranked_candidates
    WHERE transaction_choice_rank = 1
),
counted_candidates AS (
    SELECT
        *,
        count(*) OVER (PARTITION BY vat_book_entry_id) AS entry_candidate_count
    FROM transaction_best_candidates
),
matched_candidates AS (
    SELECT *
    FROM counted_candidates c
    WHERE entry_candidate_count = 1
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
        'rule', 'exact_purchase_payment_nearest_vat_book_on_or_after_payment',
        'vat_book_date', m.vat_book_date,
        'bank_operation_date', m.bank_operation_date,
        'contractor_inn', m.contractor_inn,
        'bank_amount', m.bank_amount,
        'transaction_candidate_count', m.transaction_candidate_count,
        'date_gap_days', m.vat_book_date - m.bank_operation_date
    ),
    now(),
    now()
FROM matched_candidates m
CROSS JOIN legal.accountant_report_link_types link_type
WHERE link_type.alias = 'payment_closes_vat_book_entry'
SQL;
    }

    /**
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
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
    COALESCE(dbt_currency.currency_code, dbt.currency, '643') AS currency,
    ABS(COALESCE(dbt.amount, dbt.signed_amount, 0)) AS bank_amount
FROM legal.vat_book_entries e
JOIN legal.vat_book_imports i
    ON i.vat_book_import_id = e.vat_book_import_id
JOIN legal.document_bank_transaction dbt
    ON btrim(dbt.recipient_inn::text) = btrim(e.contractor_inn::text)
JOIN legal.bank_account ba
    ON ba.bank_account_id = dbt.bank_account_id
    AND ba.legal_id = e.legal_id
LEFT JOIN legal.currency_aliases dbt_currency
    ON dbt.currency IS NOT NULL
    AND upper(btrim(dbt.currency::text)) = dbt_currency.currency_alias
WHERE i.is_active
  AND e.book_type = 'purchase'
  AND e.amount_total IS NOT NULL
  AND e.amount_total >= 0
  AND e.contractor_inn IS NOT NULL
  AND btrim(e.contractor_inn::text) <> ''
  AND COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date) IS NOT NULL
  AND dbt.operation_date IS NOT NULL
  AND btrim(dbt.account_number::text) = btrim(dbt.payer_account::text)
  AND ABS(COALESCE(dbt.amount, dbt.signed_amount, 0)) = e.amount_total
  AND dbt.operation_date <= COALESCE(e.invoice_date, e.acceptance_date, e.payment_doc_date)
  {$this->entryFilterSql($filters, 'e')}
SQL;
    }

    /**
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
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
     * @param array{legal_id?: string|null, year?: int|null, quarter?: int|null} $filters
     * @param list<mixed> $prefix
     * @return list<mixed>
     */
    private function bindings(array $filters, array $prefix = []): array
    {
        $bindings = $prefix;

        if (isset($filters['legal_id']) && $filters['legal_id'] !== null) {
            $bindings[] = (string) $filters['legal_id'];
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
