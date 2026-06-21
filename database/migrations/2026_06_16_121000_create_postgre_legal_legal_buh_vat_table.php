<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS "legal"."legal_buh_vat" (
    "reconciliation_id" bigint DEFAULT 0 NOT NULL,
    "reconciliation_type_id" smallint DEFAULT 2 NOT NULL,
    "legal_id" varchar(12) NOT NULL,
    "type" text NOT NULL,
    "year" integer NOT NULL,
    "period" text NOT NULL,
    "nomer_por" integer NOT NULL,
    "nomer_ch_f_pr" varchar(100),
    "date_ch_f" date,
    "nomer_ch_f_corr" varchar(100),
    "data_ch_f_corr" date,
    "amount_total" numeric(19, 2),
    "amount_wo_vat" numeric(19, 2),
    "amount_vat" numeric(19, 2),
    "kod_oper" varchar(30),
    "data_uch" date,
    "doc_pay_conf_number" varchar(200),
    "doc_pay_conf_date" date,
    "contractor_name" varchar(1000),
    "contractor_kpp" varchar(30),
    CONSTRAINT "legal_buh_vat_pkey" PRIMARY KEY (reconciliation_type_id, reconciliation_id),
    CONSTRAINT "legal_buh_vat_legal_id_key" UNIQUE (legal_id, type, year, period, nomer_por)
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_buh_vat" CASCADE
SQL);
    }
};
