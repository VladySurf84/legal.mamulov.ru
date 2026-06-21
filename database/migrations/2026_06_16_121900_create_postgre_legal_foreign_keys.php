<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'bank_account_ibfk_2'
    ) THEN
        ALTER TABLE "legal"."bank_account"
            ADD CONSTRAINT "bank_account_ibfk_2" FOREIGN KEY (legal_id) REFERENCES legal.legal_own(legal_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'bank_account_ibfk_3'
    ) THEN
        ALTER TABLE "legal"."bank_account"
            ADD CONSTRAINT "bank_account_ibfk_3" FOREIGN KEY (bank_id) REFERENCES legal.bank(bank_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'bank_transaction_ibfk_1'
    ) THEN
        ALTER TABLE "legal"."bank_transaction"
            ADD CONSTRAINT "bank_transaction_ibfk_1" FOREIGN KEY (account_number, bank_id) REFERENCES legal.bank_account(account_number, bank_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'bank_transaction_link'
    ) THEN
        ALTER TABLE "legal"."bank_transaction"
            ADD CONSTRAINT "bank_transaction_link" FOREIGN KEY (reconciliation_type_id, reconciliation_id) REFERENCES legal.legal_reconciliation(reconciliation_type_id, reconciliation_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'bank_transaction_1c_ibfk_1'
    ) THEN
        ALTER TABLE "legal"."bank_transaction_1c"
            ADD CONSTRAINT "bank_transaction_1c_ibfk_1" FOREIGN KEY (bank_transaction_id) REFERENCES legal.bank_transaction(bank_transaction_id) ON UPDATE CASCADE ON DELETE CASCADE;
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'kassa_ibfk_2'
    ) THEN
        ALTER TABLE "legal"."kassa"
            ADD CONSTRAINT "kassa_ibfk_2" FOREIGN KEY (article_id) REFERENCES legal.kassa_article(article_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'legal_brand_ibfk_1'
    ) THEN
        ALTER TABLE "legal"."legal_brand"
            ADD CONSTRAINT "legal_brand_ibfk_1" FOREIGN KEY (legal_id_owner) REFERENCES legal.legal_own(legal_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'legal_buh_saldo_ibfk_1'
    ) THEN
        ALTER TABLE "legal"."legal_buh_saldo"
            ADD CONSTRAINT "legal_buh_saldo_ibfk_1" FOREIGN KEY (legal_id) REFERENCES legal.legal_own(legal_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'legal_buh_vat_ibfk_1'
    ) THEN
        ALTER TABLE "legal"."legal_buh_vat"
            ADD CONSTRAINT "legal_buh_vat_ibfk_1" FOREIGN KEY (reconciliation_type_id, reconciliation_id) REFERENCES legal.legal_reconciliation(reconciliation_type_id, reconciliation_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'legal_buh_vat_ibfk_2'
    ) THEN
        ALTER TABLE "legal"."legal_buh_vat"
            ADD CONSTRAINT "legal_buh_vat_ibfk_2" FOREIGN KEY (legal_id) REFERENCES legal.legal_own(legal_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'legal_kudir_record_ibfk_1'
    ) THEN
        ALTER TABLE "legal"."legal_kudir_record"
            ADD CONSTRAINT "legal_kudir_record_ibfk_1" FOREIGN KEY (legal_id, year) REFERENCES legal.legal_tax_period(legal_id, year);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'legal_kudir_record_ibfk_2'
    ) THEN
        ALTER TABLE "legal"."legal_kudir_record"
            ADD CONSTRAINT "legal_kudir_record_ibfk_2" FOREIGN KEY (kudir_record_type_id) REFERENCES legal.legal_kudir_record_type(kudir_record_type_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'legal_reconciliation_ibfk_1'
    ) THEN
        ALTER TABLE "legal"."legal_reconciliation"
            ADD CONSTRAINT "legal_reconciliation_ibfk_1" FOREIGN KEY (reconciliation_type_id) REFERENCES legal.legal_reconciliation_type(reconciliation_type_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'legal_reconciliation_ibfk_2'
    ) THEN
        ALTER TABLE "legal"."legal_reconciliation"
            ADD CONSTRAINT "legal_reconciliation_ibfk_2" FOREIGN KEY (legal_id) REFERENCES legal.legal_own(legal_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'legal_tax_ibfk_1'
    ) THEN
        ALTER TABLE "legal"."legal_tax"
            ADD CONSTRAINT "legal_tax_ibfk_1" FOREIGN KEY (legal_id) REFERENCES legal.legal_own(legal_id);
    END IF;
END;
$$
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'legal_tax_period_ibfk_1'
    ) THEN
        ALTER TABLE "legal"."legal_tax_period"
            ADD CONSTRAINT "legal_tax_period_ibfk_1" FOREIGN KEY (legal_id) REFERENCES legal.legal_own(legal_id);
    END IF;
END;
$$
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE "legal"."legal_tax_period" DROP CONSTRAINT IF EXISTS "legal_tax_period_ibfk_1"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."legal_tax" DROP CONSTRAINT IF EXISTS "legal_tax_ibfk_1"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."legal_reconciliation" DROP CONSTRAINT IF EXISTS "legal_reconciliation_ibfk_2"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."legal_reconciliation" DROP CONSTRAINT IF EXISTS "legal_reconciliation_ibfk_1"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."legal_kudir_record" DROP CONSTRAINT IF EXISTS "legal_kudir_record_ibfk_2"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."legal_kudir_record" DROP CONSTRAINT IF EXISTS "legal_kudir_record_ibfk_1"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."legal_buh_vat" DROP CONSTRAINT IF EXISTS "legal_buh_vat_ibfk_2"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."legal_buh_vat" DROP CONSTRAINT IF EXISTS "legal_buh_vat_ibfk_1"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."legal_buh_saldo" DROP CONSTRAINT IF EXISTS "legal_buh_saldo_ibfk_1"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."legal_brand" DROP CONSTRAINT IF EXISTS "legal_brand_ibfk_1"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."kassa" DROP CONSTRAINT IF EXISTS "kassa_ibfk_2"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."bank_transaction_1c" DROP CONSTRAINT IF EXISTS "bank_transaction_1c_ibfk_1"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."bank_transaction" DROP CONSTRAINT IF EXISTS "bank_transaction_link"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."bank_transaction" DROP CONSTRAINT IF EXISTS "bank_transaction_ibfk_1"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."bank_account" DROP CONSTRAINT IF EXISTS "bank_account_ibfk_3"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."bank_account" DROP CONSTRAINT IF EXISTS "bank_account_ibfk_2"
SQL);
    }
};
