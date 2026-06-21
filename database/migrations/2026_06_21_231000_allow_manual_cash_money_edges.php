<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.money_edges
    ALTER COLUMN source_document_bank_transaction_id DROP NOT NULL
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DELETE FROM legal.money_edges
WHERE source_document_bank_transaction_id IS NULL
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.money_edges
    ALTER COLUMN source_document_bank_transaction_id SET NOT NULL
SQL);
    }
};
