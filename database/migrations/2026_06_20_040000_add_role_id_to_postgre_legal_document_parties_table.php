<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    ADD COLUMN IF NOT EXISTS "document_party_role_id" smallint
SQL);

        DB::statement(<<<'SQL'
UPDATE "legal"."document_parties" dp
SET "document_party_role_id" = dpr."document_party_role_id"
FROM "legal"."document_party_roles" dpr
WHERE dp."role" = dpr."code"
  AND dp."document_party_role_id" IS NULL
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM "legal"."document_parties" dp
        LEFT JOIN "legal"."document_party_roles" dpr ON dpr."code" = dp."role"
        WHERE dpr."document_party_role_id" IS NULL
    ) THEN
        RAISE EXCEPTION 'Cannot link document_parties to document_party_roles: unknown role exists';
    END IF;
END
$$
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    ALTER COLUMN "document_party_role_id" SET NOT NULL
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'document_parties_document_party_role_id_fkey'
    ) THEN
        ALTER TABLE "legal"."document_parties"
            ADD CONSTRAINT "document_parties_document_party_role_id_fkey"
            FOREIGN KEY ("document_party_role_id")
            REFERENCES "legal"."document_party_roles"("document_party_role_id")
            ON UPDATE CASCADE
            ON DELETE RESTRICT;
    END IF;
END
$$
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS "document_parties_document_party_role_id_idx"
    ON "legal"."document_parties" ("document_party_role_id")
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    DROP CONSTRAINT IF EXISTS "document_parties_document_party_role_id_fkey"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."document_parties_document_party_role_id_idx"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    DROP COLUMN IF EXISTS "document_party_role_id"
SQL);
    }
};
