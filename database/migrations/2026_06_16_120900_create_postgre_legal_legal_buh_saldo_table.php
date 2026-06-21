<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS "legal"."legal_buh_saldo" (
    "legal_id" varchar(12) NOT NULL,
    "contractor_inn" char(12) NOT NULL,
    "saldo" numeric(19, 2),
    CONSTRAINT "legal_buh_saldo_pkey" PRIMARY KEY (legal_id, contractor_inn)
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_buh_saldo" CASCADE
SQL);
    }
};
