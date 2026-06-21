<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_bank_transaction"
    DROP CONSTRAINT IF EXISTS "document_bank_transaction_bank_transaction_id_fkey",
    DROP COLUMN IF EXISTS "bank_transaction_id"
SQL);

        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."bank_transaction_1c" CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_buh_vat" CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_buh_saldo" CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."bank_transaction" CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_reconciliation" CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_reconciliation_type" CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_inn" CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_kudir_record" CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_kudir_record_type" CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."bank_transaction_set_defaults"() CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_buh_vat_after_delete"() CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_buh_vat_set_type"() CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_reconciliation_after_write"() CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_reconciliation_before_write"() CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_reconciliation_refresh_saldo"(bigint, bigint) CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_reconciliation_refresh_saldo"(bigint, character) CASCADE
SQL);
    }

    public function down(): void
    {
        // Legacy storage tables were intentionally removed after the project moved to the layered model.
    }
};
