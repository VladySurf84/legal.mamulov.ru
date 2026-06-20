<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS "accountant_report_links_entry_transaction_key"
    ON "legal"."accountant_report_links" ("vat_book_entry_id", "document_bank_transaction_id")
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."accountant_report_links_entry_transaction_key"
SQL);
    }
};
