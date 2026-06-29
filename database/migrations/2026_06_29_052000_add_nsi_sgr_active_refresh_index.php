<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS nsi_sgr_records_active_detail_refresh_idx
ON legal.nsi_sgr_records (detail_synced_at, nsi_sgr_record_id)
WHERE status_id = '0888035a-52fa-4e7e-bf59-348c6cc218d4'::uuid
    AND detail_payload IS NOT NULL
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS legal.nsi_sgr_records_active_detail_refresh_idx');
    }
};
