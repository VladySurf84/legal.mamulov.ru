<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
WITH marketplace_rules AS (
    SELECT *
    FROM (VALUES
        ('Озон → ИП Рыбникова', '771548701079', '7704217370', 'Озон', 'Поступление от Озон'),
        ('Вайлдберриз (РВБ) → ИП Рыбникова', '771548701079', '9714053621', 'Вайлдберриз', 'Поступление от Вайлдберриз (РВБ)')
    ) AS rule(name, legal_id, contractor_inn, article, description_template)
),
resolved AS (
    SELECT
        marketplace_rules.name,
        marketplace_rules.legal_id,
        marketplace_rules.contractor_inn,
        article.article_id,
        marketplace_rules.description_template
    FROM marketplace_rules
    JOIN legal.legal_own legal
        ON legal.legal_id = marketplace_rules.legal_id
    JOIN legal.kassa_article article
        ON article.article = marketplace_rules.article
)
INSERT INTO legal.cash_operation_rules (
    name,
    legal_id,
    contractor_inn,
    direction,
    article_id,
    description_template,
    priority,
    is_active,
    created_at,
    updated_at
)
SELECT
    resolved.name,
    resolved.legal_id,
    resolved.contractor_inn,
    'incoming',
    resolved.article_id,
    resolved.description_template,
    100,
    true,
    now(),
    now()
FROM resolved
WHERE NOT EXISTS (
    SELECT 1
    FROM legal.cash_operation_rules existing
    WHERE existing.legal_id = resolved.legal_id
        AND existing.contractor_inn = resolved.contractor_inn
        AND existing.direction = 'incoming'
);
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DELETE FROM legal.cash_operation_rules
WHERE legal_id = '771548701079'
    AND contractor_inn IN ('7704217370', '9714053621')
    AND direction = 'incoming'
    AND name IN ('Озон → ИП Рыбникова', 'Вайлдберриз (РВБ) → ИП Рыбникова')
SQL);
    }
};
