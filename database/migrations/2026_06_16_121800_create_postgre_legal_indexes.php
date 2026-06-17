<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS bank_account_account_number_idx ON legal.bank_account USING btree (account_number)
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS bank_account_bank_id_idx ON legal.bank_account USING btree (bank_id)
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS bank_account_legal_id_idx ON legal.bank_account USING btree (legal_id)
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS bank_transaction_account_number_idx ON legal.bank_transaction USING btree (account_number, bank_id)
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS kassa_amount_idx ON legal.kassa USING btree (amount)
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS kassa_article_id_idx ON legal.kassa USING btree (article_id)
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS kassa_user_id_idx ON legal.kassa USING btree (user_id, "time")
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS legal_brand_legal_id_idx ON legal.legal_brand USING btree (legal_id_owner)
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS legal_kudir_record_kudir_record_type_id_idx ON legal.legal_kudir_record USING btree (kudir_record_type_id)
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS legal_kudir_record_legal_id_idx ON legal.legal_kudir_record USING btree (legal_id, year)
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS legal_reconciliation_date_idx ON legal.legal_reconciliation USING btree (date)
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS legal_reconciliation_legal_id_idx ON legal.legal_reconciliation USING btree (legal_id)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."legal_reconciliation_legal_id_idx"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."legal_reconciliation_date_idx"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."legal_kudir_record_legal_id_idx"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."legal_kudir_record_kudir_record_type_id_idx"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."legal_brand_legal_id_idx"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."kassa_user_id_idx"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."kassa_article_id_idx"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."kassa_amount_idx"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."bank_transaction_account_number_idx"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."bank_account_legal_id_idx"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."bank_account_bank_id_idx"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."bank_account_account_number_idx"
SQL);
    }
};
