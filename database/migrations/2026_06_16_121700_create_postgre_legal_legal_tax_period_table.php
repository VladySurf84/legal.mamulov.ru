<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS "legal"."legal_tax_period" (
    "legal_id" varchar(12) NOT NULL,
    "year" integer NOT NULL,
    "tax_system" text NOT NULL,
    "usn_doh" numeric(10, 2) NOT NULL,
    "usn_doh_ras" numeric(10, 2) NOT NULL,
    "vat_20" integer DEFAULT 0,
    "vat_5" integer DEFAULT 0 NOT NULL,
    CONSTRAINT "legal_tax_period_pkey" PRIMARY KEY (legal_id, year)
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_tax_period" CASCADE
SQL);
    }
};
