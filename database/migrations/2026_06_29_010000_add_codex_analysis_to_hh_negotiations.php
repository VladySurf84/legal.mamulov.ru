<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.hh_negotiations
    ADD COLUMN IF NOT EXISTS codex_analyzed_at timestamp(0) without time zone,
    ADD COLUMN IF NOT EXISTS codex_analysis_score integer,
    ADD COLUMN IF NOT EXISTS codex_analysis_summary text,
    ADD COLUMN IF NOT EXISTS codex_analysis_flags jsonb
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS hh_negotiations_codex_score_idx ON legal.hh_negotiations (codex_analysis_score DESC NULLS LAST)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS legal.hh_negotiations_codex_score_idx');
        DB::statement(<<<'SQL'
ALTER TABLE legal.hh_negotiations
    DROP COLUMN IF EXISTS codex_analysis_flags,
    DROP COLUMN IF EXISTS codex_analysis_summary,
    DROP COLUMN IF EXISTS codex_analysis_score,
    DROP COLUMN IF EXISTS codex_analyzed_at
SQL);
    }
};
