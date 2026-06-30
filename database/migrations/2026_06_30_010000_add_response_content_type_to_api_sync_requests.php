<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.api_sync_requests
ADD COLUMN IF NOT EXISTS response_content_type varchar(255)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.api_sync_requests
DROP COLUMN IF EXISTS response_content_type
SQL);
    }
};
