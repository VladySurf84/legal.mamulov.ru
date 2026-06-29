<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.nsi_sgr_records
    ALTER COLUMN product_code TYPE text
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.nsi_sgr_records
    ALTER COLUMN product_code TYPE varchar(100) USING left(product_code, 100)
SQL);
    }
};
