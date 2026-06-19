<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    DROP CONSTRAINT IF EXISTS "document_parties_role_fkey"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    DROP CONSTRAINT IF EXISTS "document_parties_document_role_index_key"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    DROP CONSTRAINT IF EXISTS "document_parties_role_not_empty_check"
SQL);

        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS "legal"."document_parties_role_idx"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    ADD CONSTRAINT "document_parties_document_role_id_index_key"
    UNIQUE ("document_id", "document_party_role_id", "role_index")
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    DROP COLUMN IF EXISTS "role"
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    ADD COLUMN IF NOT EXISTS "role" varchar(100)
SQL);

        DB::statement(<<<'SQL'
UPDATE "legal"."document_parties" dp
SET "role" = dpr."code"
FROM "legal"."document_party_roles" dpr
WHERE dp."document_party_role_id" = dpr."document_party_role_id"
  AND dp."role" IS NULL
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    ALTER COLUMN "role" SET NOT NULL
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    DROP CONSTRAINT IF EXISTS "document_parties_document_role_id_index_key"
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    ADD CONSTRAINT "document_parties_document_role_index_key"
    UNIQUE ("document_id", "role", "role_index")
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE "legal"."document_parties"
    ADD CONSTRAINT "document_parties_role_not_empty_check"
    CHECK (btrim(role::text) <> '')
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS "document_parties_role_idx"
    ON "legal"."document_parties" ("role")
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'document_parties_role_fkey'
    ) THEN
        ALTER TABLE "legal"."document_parties"
            ADD CONSTRAINT "document_parties_role_fkey"
            FOREIGN KEY ("role")
            REFERENCES "legal"."document_party_roles"("code")
            ON UPDATE CASCADE
            ON DELETE RESTRICT
            NOT VALID;
    END IF;
END
$$
SQL);
    }
};
