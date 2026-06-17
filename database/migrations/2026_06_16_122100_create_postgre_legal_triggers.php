<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "bank_transaction_b_i" ON "legal"."bank_transaction"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER bank_transaction_b_i BEFORE INSERT ON legal.bank_transaction FOR EACH ROW EXECUTE FUNCTION legal.bank_transaction_set_defaults();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "bank_transaction_b_u" ON "legal"."bank_transaction"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER bank_transaction_b_u BEFORE UPDATE ON legal.bank_transaction FOR EACH ROW EXECUTE FUNCTION legal.bank_transaction_set_defaults();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "bank_transaction_1c_b_i" ON "legal"."bank_transaction_1c"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER bank_transaction_1c_b_i BEFORE INSERT ON legal.bank_transaction_1c FOR EACH ROW EXECUTE FUNCTION legal.bank_transaction_1c_set_hash();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "bank_transaction_1c_b_u" ON "legal"."bank_transaction_1c"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER bank_transaction_1c_b_u BEFORE UPDATE ON legal.bank_transaction_1c FOR EACH ROW EXECUTE FUNCTION legal.bank_transaction_1c_set_hash();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_bug_vat_a_d" ON "legal"."legal_buh_vat"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER legal_bug_vat_a_d AFTER DELETE ON legal.legal_buh_vat FOR EACH ROW EXECUTE FUNCTION legal.legal_buh_vat_after_delete();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_bug_vat_b_u" ON "legal"."legal_buh_vat"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER legal_bug_vat_b_u BEFORE UPDATE ON legal.legal_buh_vat FOR EACH ROW EXECUTE FUNCTION legal.legal_buh_vat_set_type();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_buh_vat_b_i" ON "legal"."legal_buh_vat"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER legal_buh_vat_b_i BEFORE INSERT ON legal.legal_buh_vat FOR EACH ROW EXECUTE FUNCTION legal.legal_buh_vat_set_type();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_kudir_record__b_i" ON "legal"."legal_kudir_record"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER legal_kudir_record__b_i BEFORE INSERT ON legal.legal_kudir_record FOR EACH ROW EXECUTE FUNCTION legal.legal_kudir_record_set_year();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_kudir_record__b_u" ON "legal"."legal_kudir_record"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER legal_kudir_record__b_u BEFORE UPDATE ON legal.legal_kudir_record FOR EACH ROW EXECUTE FUNCTION legal.legal_kudir_record_set_year();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_reconciliation_a_d" ON "legal"."legal_reconciliation"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER legal_reconciliation_a_d AFTER DELETE ON legal.legal_reconciliation FOR EACH ROW EXECUTE FUNCTION legal.legal_reconciliation_after_write();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_reconciliation_a_i" ON "legal"."legal_reconciliation"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER legal_reconciliation_a_i AFTER INSERT ON legal.legal_reconciliation FOR EACH ROW EXECUTE FUNCTION legal.legal_reconciliation_after_write();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_reconciliation_a_u" ON "legal"."legal_reconciliation"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER legal_reconciliation_a_u AFTER UPDATE ON legal.legal_reconciliation FOR EACH ROW EXECUTE FUNCTION legal.legal_reconciliation_after_write();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_reconciliation_b_i" ON "legal"."legal_reconciliation"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER legal_reconciliation_b_i BEFORE INSERT ON legal.legal_reconciliation FOR EACH ROW EXECUTE FUNCTION legal.legal_reconciliation_before_write();
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_reconciliation_b_u" ON "legal"."legal_reconciliation"
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER legal_reconciliation_b_u BEFORE UPDATE ON legal.legal_reconciliation FOR EACH ROW EXECUTE FUNCTION legal.legal_reconciliation_before_write();
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_reconciliation_b_u" ON "legal"."legal_reconciliation"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_reconciliation_b_i" ON "legal"."legal_reconciliation"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_reconciliation_a_u" ON "legal"."legal_reconciliation"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_reconciliation_a_i" ON "legal"."legal_reconciliation"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_reconciliation_a_d" ON "legal"."legal_reconciliation"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_kudir_record__b_u" ON "legal"."legal_kudir_record"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_kudir_record__b_i" ON "legal"."legal_kudir_record"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_buh_vat_b_i" ON "legal"."legal_buh_vat"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_bug_vat_b_u" ON "legal"."legal_buh_vat"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "legal_bug_vat_a_d" ON "legal"."legal_buh_vat"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "bank_transaction_1c_b_u" ON "legal"."bank_transaction_1c"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "bank_transaction_1c_b_i" ON "legal"."bank_transaction_1c"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "bank_transaction_1c_a_i" ON "legal"."bank_transaction_1c"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "bank_transaction_b_u" ON "legal"."bank_transaction"
SQL);

        DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS "bank_transaction_b_i" ON "legal"."bank_transaction"
SQL);
    }
};
