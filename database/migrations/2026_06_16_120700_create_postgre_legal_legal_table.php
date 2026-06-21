<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS "legal"."legal_own" (
    "legal_id" varchar(12) NOT NULL,
    "legal_name" varchar(500) NOT NULL,
    "legal_fullname" varchar(500) NOT NULL,
    "legal_letter" char(1) NOT NULL,
    "firstname" varchar(200) NOT NULL,
    "lastname" varchar(200) NOT NULL,
    "middlename" varchar(200) NOT NULL,
    "legal_color" varchar(30) NOT NULL,
    "tax_system" text NOT NULL,
    "tax_rate" numeric(10, 2) NOT NULL,
    "vat_rate" smallint,
    "legal_inn" varchar(12) NOT NULL,
    "legal_ogrn" bigint NOT NULL,
    "legal_comment" text,
    "addr_index" bigint,
    "addr_region_code" varchar(3),
    "addr_region" varchar(500),
    "addr_town" varchar(500),
    "addr_street" varchar(500),
    "addr_house" varchar(50),
    "addr_flat" varchar(50),
    "suz_conn_id" char(36),
    "suz_oms_id" char(36),
    "cert_cn" varchar(500),
    "edo_light_id" varchar(100),
    "cdek_client_id" varchar(500),
    "cdek_client_secret" varchar(500),
    "cdek_token" varchar(3000),
    "cdek_token_expired" timestamp without time zone,
    "dellin_session_id" char(36),
    "tax_periods" jsonb DEFAULT '{}'::jsonb NOT NULL,
    CONSTRAINT "legal_pkey" PRIMARY KEY (legal_id)
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_own" CASCADE
SQL);
    }
};
