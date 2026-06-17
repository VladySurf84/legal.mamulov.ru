<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS "legal"."legal_inn" (
    "legal_inn" char(12) NOT NULL,
    "legal_name" varchar(500),
    "saldo" numeric(19, 2),
    "legal_short" varchar(200),
    CONSTRAINT "legal_inn_pkey" PRIMARY KEY (legal_inn)
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_inn" CASCADE
SQL);
    }
};
