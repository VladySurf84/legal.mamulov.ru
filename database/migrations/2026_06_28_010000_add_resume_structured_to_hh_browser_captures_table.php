<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.hh_browser_captures
    ADD COLUMN IF NOT EXISTS resume_structured jsonb NOT NULL DEFAULT '{}'::jsonb
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS hh_browser_captures_resume_structured_gin_idx ON legal.hh_browser_captures USING gin (resume_structured)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS legal.hh_browser_captures_resume_structured_gin_idx');
        DB::statement('ALTER TABLE legal.hh_browser_captures DROP COLUMN IF EXISTS resume_structured');
    }
};