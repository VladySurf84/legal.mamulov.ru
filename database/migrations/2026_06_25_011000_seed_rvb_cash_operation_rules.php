<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
WITH article AS (
    SELECT article_id
    FROM legal.kassa_article
    WHERE article = 'Вайлдберриз'
    LIMIT 1
),
rules AS (
    SELECT
        legal.legal_id,
        '9714053621'::varchar(12) AS contractor_inn,
        'Вайлдберриз (РВБ) → ' || legal.legal_name AS name,
        article.article_id
    FROM legal.legal_own legal
    CROSS JOIN article
    WHERE legal.legal_id IN ('504025873801', '521001553172')
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
    rules.name,
    rules.legal_id,
    rules.contractor_inn,
    'incoming',
    rules.article_id,
    'Поступление от Вайлдберриз (РВБ)',
    100,
    true,
    now(),
    now()
FROM rules
WHERE NOT EXISTS (
    SELECT 1
    FROM legal.cash_operation_rules existing
    WHERE existing.legal_id = rules.legal_id
        AND existing.contractor_inn = rules.contractor_inn
        AND existing.direction = 'incoming'
);
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DELETE FROM legal.cash_operation_rules
WHERE contractor_inn = '9714053621'
    AND legal_id IN ('504025873801', '521001553172')
    AND direction = 'incoming'
    AND name LIKE 'Вайлдберриз (РВБ)%'
SQL);
    }
};
