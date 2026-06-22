<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.api_sync_runs
    ADD COLUMN IF NOT EXISTS started_by_type varchar(20) NOT NULL DEFAULT 'console',
    ADD COLUMN IF NOT EXISTS started_by_user_id bigint,
    ADD COLUMN IF NOT EXISTS started_from varchar(50)
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'api_sync_runs_started_by_type_check'
            AND conrelid = 'legal.api_sync_runs'::regclass
    ) THEN
        ALTER TABLE legal.api_sync_runs
            ADD CONSTRAINT api_sync_runs_started_by_type_check
            CHECK (started_by_type IN ('system', 'user', 'console'));
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'api_sync_runs_started_by_user_id_fkey'
            AND conrelid = 'legal.api_sync_runs'::regclass
    ) THEN
        ALTER TABLE legal.api_sync_runs
            ADD CONSTRAINT api_sync_runs_started_by_user_id_fkey
            FOREIGN KEY (started_by_user_id)
            REFERENCES legal.laravel_users(id)
            ON UPDATE CASCADE
            ON DELETE SET NULL;
    END IF;
END $$;
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS api_sync_runs_started_by_idx ON legal.api_sync_runs (started_by_type, started_by_user_id)');
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.api_sync_runs
    DROP CONSTRAINT IF EXISTS api_sync_runs_started_by_user_id_fkey,
    DROP CONSTRAINT IF EXISTS api_sync_runs_started_by_type_check
SQL);

        DB::statement('DROP INDEX IF EXISTS legal.api_sync_runs_started_by_idx');

        DB::statement(<<<'SQL'
ALTER TABLE legal.api_sync_runs
    DROP COLUMN IF EXISTS started_from,
    DROP COLUMN IF EXISTS started_by_user_id,
    DROP COLUMN IF EXISTS started_by_type
SQL);
    }
};
