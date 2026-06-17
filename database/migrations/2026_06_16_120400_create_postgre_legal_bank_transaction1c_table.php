<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS "legal"."bank_transaction_1c" (
    "transaction_hash" char(32) DEFAULT ''::bpchar NOT NULL,
    "bank_transaction_id" bigint NOT NULL,
    "1c_bank_id" char(9) NOT NULL,
    "1c_account_number" char(20) NOT NULL,
    "operation_id" varchar(100) NOT NULL,
    "id" varchar(100),
    "1c_date" date,
    "1c_amount" numeric(10, 2),
    "draw_date" date,
    "payer_name" varchar(1000),
    "payer_inn" char(12),
    "payer_account" char(20),
    "payer_corr_account" char(20),
    "payer_bic" char(9),
    "payer_bank" varchar(500),
    "charge_cate" date,
    "recipient" varchar(500),
    "recipient_inn" char(12),
    "recipient_account" char(20),
    "recipient_corr_account" char(20),
    "recipient_bic" char(9),
    "recipient_bank" char(200),
    "payment_type" varchar(100),
    "operation_type" char(2),
    "uin" varchar(100),
    "1c_payment_purpose" varchar(4000),
    "creator_status" varchar(100),
    "payer_kpp" char(9),
    "recipient_kpp" char(9),
    "kbk" varchar(100),
    "oktmo" varchar(100),
    "tax_evidence" varchar(100),
    "tax_period" varchar(100),
    "tax_doc_number" varchar(100),
    "tax_doc_date" varchar(100),
    "tax_type" varchar(100),
    "execution_order" varchar(5),
    CONSTRAINT "bank_transaction_1c_pkey" PRIMARY KEY (transaction_hash),
    CONSTRAINT "bank_transaction_1c_account_number_key" UNIQUE ("1c_account_number", "1c_bank_id", operation_id),
    CONSTRAINT "bank_transaction_1c_bank_transaction_id_key" UNIQUE (bank_transaction_id)
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."bank_transaction_1c" CASCADE
SQL);
    }
};
