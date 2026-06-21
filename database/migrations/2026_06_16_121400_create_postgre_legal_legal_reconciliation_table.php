<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS "legal"."legal_reconciliation" (
    "reconciliation_id" bigint NOT NULL,
    "reconciliation_type_id" smallint NOT NULL,
    "legal_id" varchar(12) NOT NULL,
    "date" date NOT NULL,
    "amount" numeric(15, 2) NOT NULL,
    "contractor_inn" char(12),
    CONSTRAINT "legal_reconciliation_pkey" PRIMARY KEY (reconciliation_id),
    CONSTRAINT "legal_reconciliation_reconciliation_type_id_key" UNIQUE (reconciliation_type_id, reconciliation_id)
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_reconciliation" CASCADE
SQL);
    }
};
