<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
INSERT INTO legal.kassa_article (article)
VALUES ('Вайлдберриз'), ('Озон')
ON CONFLICT (article) DO NOTHING
SQL);

        DB::statement(<<<'SQL'
INSERT INTO legal.cash_operation_rules (
    name,
    legal_id,
    contractor_inn,
    article_id,
    direction,
    priority,
    description_template,
    metadata
)
SELECT
    article.article || ' → ' || legal.legal_name,
    legal.legal_id,
    marketplace.contractor_inn,
    article.article_id,
    'incoming',
    100,
    'Поступление от ' || article.article,
    jsonb_build_object('seeded', true, 'marketplace', marketplace.code)
FROM (VALUES
    ('wb', '7721546864', 'Вайлдберриз'),
    ('ozon', '7704217370', 'Озон')
) AS marketplace(code, contractor_inn, article_name)
JOIN legal.kassa_article article
    ON article.article = marketplace.article_name
JOIN legal.legal_own legal
    ON legal.legal_id IN ('504025873801', '521001553172')
WHERE NOT EXISTS (
    SELECT 1
    FROM legal.cash_operation_rules existing
    WHERE existing.legal_id = legal.legal_id
        AND existing.contractor_inn = marketplace.contractor_inn
        AND existing.article_id = article.article_id
        AND existing.direction = 'incoming'
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DELETE FROM legal.cash_operation_rules
WHERE metadata->>'seeded' = 'true'
    AND metadata->>'marketplace' IN ('wb', 'ozon')
    AND legal_id IN ('504025873801', '521001553172')
    AND contractor_inn IN ('7721546864', '7704217370')
SQL);
    }
};
