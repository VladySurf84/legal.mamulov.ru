<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
WITH resolved AS (
    SELECT
        'Вайлдберриз → ИП Рыбникова'::varchar(255) AS name,
        '771548701079'::varchar(12) AS legal_id,
        '7721546864'::varchar(12) AS contractor_inn,
        article.article_id,
        'Поступление от Вайлдберриз'::varchar(255) AS description_template
    FROM legal.kassa_article article
    JOIN legal.legal_own legal
        ON legal.legal_id = '771548701079'
    WHERE article.article = 'Вайлдберриз'
    LIMIT 1
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
    AND contractor_inn = '7721546864'
    AND direction = 'incoming'
    AND name = 'Вайлдберриз → ИП Рыбникова'
SQL);
    }
};
