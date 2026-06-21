<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS "legal"."legal_tax" (
    "legal_id" varchar(12) NOT NULL,
    "year" integer DEFAULT 0 NOT NULL,
    "quarter" text NOT NULL,
    "income" numeric(10, 2),
    "cost" numeric(10, 2),
    "income_declaration" numeric(10, 2),
    "cost_declaration" numeric(10, 2),
    "base_declaration" numeric(10, 2),
    "description" varchar(2000),
    CONSTRAINT "legal_tax_pkey" PRIMARY KEY (legal_id, year, quarter)
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_tax" CASCADE
SQL);
    }
};
