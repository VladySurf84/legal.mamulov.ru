<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE "legal"."vat_events"
    ADD COLUMN IF NOT EXISTS "source_document_id" bigint,
    ADD COLUMN IF NOT EXISTS "source_document_bank_transaction_id" bigint
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'vat_events_source_document_id_fkey'
          AND conrelid = 'legal.vat_events'::regclass
    ) THEN
        ALTER TABLE "legal"."vat_events"
            ADD CONSTRAINT "vat_events_source_document_id_fkey"
            FOREIGN KEY ("source_document_id")
            REFERENCES "legal"."documents"("document_id")
            ON UPDATE CASCADE
            ON DELETE CASCADE;
    END IF;
END $$;
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'vat_events_source_document_bank_transaction_id_fkey'
          AND conrelid = 'legal.vat_events'::regclass
    ) THEN
        ALTER TABLE "legal"."vat_events"
            ADD CONSTRAINT "vat_events_source_document_bank_transaction_id_fkey"
            FOREIGN KEY ("source_document_bank_transaction_id")
            REFERENCES "legal"."document_bank_transaction"("document_bank_transaction_id")
            ON UPDATE CASCADE
            ON DELETE CASCADE;
    END IF;
END $$;
SQL);

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS "vat_events_unique_source_bank_transaction"
    ON "legal"."vat_events" ("source_system", "source_document_bank_transaction_id")
    WHERE "source_document_bank_transaction_id" IS NOT NULL
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."vat_events_unique_source_bank_transaction"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."vat_events"
    DROP CONSTRAINT IF EXISTS "vat_events_source_document_bank_transaction_id_fkey",
    DROP CONSTRAINT IF EXISTS "vat_events_source_document_id_fkey",
    DROP COLUMN IF EXISTS "source_document_bank_transaction_id",
    DROP COLUMN IF EXISTS "source_document_id"
SQL);
    }
};
