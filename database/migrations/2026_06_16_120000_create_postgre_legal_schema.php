<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE SCHEMA IF NOT EXISTS "legal"
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP SCHEMA IF EXISTS "legal" CASCADE
SQL);
    }
};
