<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS "legal"."bank_account" (
    "account_number" char(20) NOT NULL,
    "bank_id" char(9) NOT NULL,
    "legal_id" bigint NOT NULL,
    "name" varchar(300) NOT NULL,
    "currency" varchar(50) NOT NULL,
    "account_type" varchar(100) NOT NULL,
    "activation_date" date,
    "balance_otb" numeric(10, 2),
    "balance_authorized" numeric(10, 2),
    "balance_pending_payments" numeric(10, 2),
    "balance_pending_requisitions" numeric(10, 2),
    CONSTRAINT "bank_account_pkey" PRIMARY KEY (account_number, bank_id)
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."bank_account" CASCADE
SQL);
    }
};
