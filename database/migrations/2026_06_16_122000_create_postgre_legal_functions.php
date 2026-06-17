<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION legal.bank_transaction_1c_set_hash()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
  NEW.transaction_hash := md5(CONCAT(NEW."1c_bank_id", '-', NEW."1c_account_number", '-', NEW.operation_id));
  RETURN NEW;
END;
$function$
;
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION legal.bank_transaction_set_defaults()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
  NEW.reconciliation_type_id := 1;
  NEW.has_vat := CASE
    WHEN (
      COALESCE(NEW.payment_purpose, '') LIKE E'%НДС%\\%%' ESCAPE '\'
      OR COALESCE(NEW.payment_purpose, '') LIKE '%в том числе НДС%'
    ) THEN 1
    ELSE 0
  END;
  RETURN NEW;
END;
$function$
;
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION legal.legal_buh_vat_after_delete()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
  DELETE FROM "legal".legal_reconciliation WHERE reconciliation_id = OLD.reconciliation_id;
  RETURN OLD;
END;
$function$
;
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION legal.legal_buh_vat_set_type()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
  NEW.reconciliation_type_id := 2;
  RETURN NEW;
END;
$function$
;
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION legal.legal_kudir_record_set_year()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
  NEW.year := EXTRACT(YEAR FROM NEW.date)::integer;
  RETURN NEW;
END;
$function$
;
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION legal.legal_reconciliation_after_write()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
  IF TG_OP IN ('UPDATE', 'DELETE') AND OLD.contractor_inn IS NOT NULL AND OLD.date >= DATE '2025-01-01' THEN
    PERFORM "legal".legal_reconciliation_refresh_saldo(OLD.legal_id, OLD.contractor_inn);
  END IF;

  IF TG_OP IN ('INSERT', 'UPDATE') AND NEW.contractor_inn IS NOT NULL AND NEW.date >= DATE '2025-01-01' THEN
    PERFORM "legal".legal_reconciliation_refresh_saldo(NEW.legal_id, NEW.contractor_inn);
  END IF;

  RETURN COALESCE(NEW, OLD);
END;
$function$
;
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION legal.legal_reconciliation_before_write()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
  IF TG_OP = 'INSERT' AND NEW.contractor_inn IS NOT NULL THEN
    INSERT INTO "legal".legal_inn (legal_inn)
    VALUES (NEW.contractor_inn)
    ON CONFLICT DO NOTHING;
  ELSIF TG_OP = 'UPDATE' AND NEW.contractor_inn IS NOT NULL AND NEW.contractor_inn IS DISTINCT FROM OLD.contractor_inn THEN
    INSERT INTO "legal".legal_inn (legal_inn)
    VALUES (NEW.contractor_inn)
    ON CONFLICT DO NOTHING;
  END IF;
  RETURN NEW;
END;
$function$
;
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION legal.legal_reconciliation_refresh_saldo(p_legal_id bigint, p_contractor_inn bigint)
 RETURNS void
 LANGUAGE plpgsql
AS $function$
BEGIN
  INSERT INTO "legal".legal_buh_saldo (legal_id, contractor_inn, saldo)
  VALUES (
    p_legal_id,
    p_contractor_inn,
    (
      SELECT SUM(amount)
      FROM "legal".legal_reconciliation
      WHERE legal_id = p_legal_id AND contractor_inn = p_contractor_inn
    )
  )
  ON CONFLICT (legal_id, contractor_inn)
  DO UPDATE SET saldo = EXCLUDED.saldo;
END;
$function$
;
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION legal.legal_reconciliation_refresh_saldo(p_legal_id bigint, p_contractor_inn character)
 RETURNS void
 LANGUAGE plpgsql
AS $function$
BEGIN
  INSERT INTO legal.legal_buh_saldo (legal_id, contractor_inn, saldo)
  VALUES (
    p_legal_id,
    p_contractor_inn,
    (
      SELECT SUM(amount)
      FROM legal.legal_reconciliation
      WHERE legal_id = p_legal_id AND contractor_inn = p_contractor_inn
    )
  )
  ON CONFLICT (legal_id, contractor_inn)
  DO UPDATE SET saldo = EXCLUDED.saldo;
END;
$function$
;
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_reconciliation_refresh_saldo"(bigint, character) CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_reconciliation_refresh_saldo"(bigint, bigint) CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_reconciliation_before_write"() CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_reconciliation_after_write"() CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_kudir_record_set_year"() CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_buh_vat_set_type"() CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."legal_buh_vat_after_delete"() CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."bank_transaction_set_defaults"() CASCADE
SQL);

        DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS "legal"."bank_transaction_1c_set_hash"() CASCADE
SQL);
    }
};
