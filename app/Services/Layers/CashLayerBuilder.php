<?php

namespace App\Services\Layers;

use Illuminate\Support\Facades\DB;

class CashLayerBuilder
{
    public function rebuild(): int
    {
        return DB::transaction(function (): int {
            DB::table('legal.cash_entries')->delete();

            $this->insertBankRuleEntries();
            $this->updateKassaCashEntryLinks();
            $this->insertManualKassaEntries();

            return DB::table('legal.cash_entries')->count();
        });
    }

    private function insertManualKassaEntries(): void
    {
        DB::insert(<<<'SQL'
INSERT INTO legal.cash_entries (
    source_type,
    source_label,
    source_document_id,
    kassa_id,
    legal_id,
    article_id,
    occurred_at,
    amount,
    currency,
    description,
    metadata,
    created_at,
    updated_at
)
SELECT
    'manual_kassa',
    'Ручной ввод',
    k.document_id,
    k.kassa_id,
    NULL::varchar(12),
    k.article_id,
    k."time",
    k.amount::numeric(18,2),
    '643',
    k.description,
    jsonb_build_object(
        'source', 'manual_kassa',
        'kassa_id', k.kassa_id,
        'article_id', k.article_id,
        'article', article.article
    ),
    now(),
    now()
FROM legal.kassa k
LEFT JOIN legal.kassa_article article
    ON article.article_id = k.article_id
WHERE NOT (
    k.reconciliation_id IS NOT NULL
    AND k.cash_entry_id IS NOT NULL
)
SQL);
    }

    private function insertBankRuleEntries(): void
    {
        DB::insert(<<<'SQL'
WITH bank_rows AS (
    SELECT
        dbt.*,
        ba.legal_id,
        CASE
            WHEN dbt.signed_amount IS NOT NULL THEN dbt.signed_amount
            WHEN btrim(dbt.account_number::text) = btrim(COALESCE(dbt.recipient_account::text, '')) THEN ABS(COALESCE(dbt.amount, 0))
            ELSE -ABS(COALESCE(dbt.amount, 0))
        END AS signed_amount_for_account,
        CASE
            WHEN dbt.signed_amount IS NOT NULL AND dbt.signed_amount >= 0 THEN dbt.payer_inn
            WHEN dbt.signed_amount IS NOT NULL AND dbt.signed_amount < 0 THEN dbt.recipient_inn
            WHEN btrim(dbt.account_number::text) = btrim(COALESCE(dbt.recipient_account::text, '')) THEN dbt.payer_inn
            ELSE dbt.recipient_inn
        END AS contractor_inn_for_account
    FROM legal.document_bank_transaction dbt
    JOIN legal.bank_account ba
        ON ba.bank_account_id = dbt.bank_account_id
),
ranked_matches AS (
    SELECT
        br.*,
        rule.cash_operation_rule_id,
        rule.name AS rule_name,
        rule.article_id AS rule_article_id,
        rule.description_template,
        article.article,
        row_number() OVER (
            PARTITION BY br.document_bank_transaction_id
            ORDER BY rule.priority, rule.cash_operation_rule_id
        ) AS match_rank
    FROM bank_rows br
    JOIN legal.cash_operation_rules rule
        ON rule.is_active
        AND rule.legal_id = br.legal_id
        AND btrim(rule.contractor_inn) = btrim(COALESCE(br.contractor_inn_for_account, ''))
        AND (
            (rule.direction = 'incoming' AND br.signed_amount_for_account > 0)
            OR (rule.direction = 'outgoing' AND br.signed_amount_for_account < 0)
        )
        AND (rule.valid_from IS NULL OR br.operation_date >= rule.valid_from)
        AND (rule.valid_to IS NULL OR br.operation_date <= rule.valid_to)
    LEFT JOIN legal.kassa_article article
        ON article.article_id = rule.article_id
    WHERE COALESCE(br.signed_amount_for_account, 0) <> 0
)
INSERT INTO legal.cash_entries (
    source_type,
    source_label,
    source_document_id,
    source_document_bank_transaction_id,
    cash_operation_rule_id,
    legal_id,
    article_id,
    occurred_at,
    amount,
    currency,
    description,
    metadata,
    created_at,
    updated_at
)
SELECT
    'bank_rule',
    'Банк: ' || ranked.rule_name,
    ranked.document_id,
    ranked.document_bank_transaction_id,
    ranked.cash_operation_rule_id,
    ranked.legal_id,
    ranked.rule_article_id,
    COALESCE(ranked.operation_date, ranked.draw_date, ranked.charge_date, now()::date)::timestamp without time zone,
    ranked.signed_amount_for_account::numeric(18,2),
    COALESCE(currency_alias.currency_code, ranked.currency, '643'),
    COALESCE(NULLIF(ranked.description_template, ''), ranked.payment_purpose, ranked.rule_name),
    jsonb_build_object(
        'source', 'bank_rule',
        'rule_id', ranked.cash_operation_rule_id,
        'rule_name', ranked.rule_name,
        'article_id', ranked.rule_article_id,
        'article', ranked.article,
        'document_bank_transaction_id', ranked.document_bank_transaction_id,
        'external_operation_id', ranked.external_operation_id,
        'contractor_inn', ranked.contractor_inn_for_account
    ),
    now(),
    now()
FROM ranked_matches ranked
LEFT JOIN legal.currency_aliases currency_alias
    ON ranked.currency IS NOT NULL
    AND upper(btrim(ranked.currency::text)) = currency_alias.currency_alias
WHERE ranked.match_rank = 1
SQL);
    }

    private function updateKassaCashEntryLinks(): void
    {
        DB::statement(<<<'SQL'
UPDATE legal.kassa k
SET cash_entry_id = (
    SELECT ce.cash_entry_id
    FROM legal.cash_entries ce
    WHERE ce.source_type = 'bank_rule'
        AND ce.amount > 0
        AND ce.metadata->>'contractor_inn' IN ('7704217370', '9714053621', '7721546864')
        AND date_trunc('second', ce.occurred_at) = date_trunc('second', k."time")
        AND ce.amount BETWEEN k.amount - 1 AND k.amount + 1
    ORDER BY abs(ce.amount - k.amount), ce.cash_entry_id
    LIMIT 1
)
WHERE k.reconciliation_id IS NOT NULL
    AND k.description ILIKE 'Выплата%'
SQL);
    }
}
