<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE SEQUENCE IF NOT EXISTS legal.bank_account_bank_account_id_seq
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_account
    ADD COLUMN IF NOT EXISTS bank_account_id bigint
SQL);

        DB::statement(<<<'SQL'
UPDATE legal.bank_account
SET bank_account_id = nextval('legal.bank_account_bank_account_id_seq')
WHERE bank_account_id IS NULL
SQL);

        DB::statement(<<<'SQL'
SELECT setval(
    'legal.bank_account_bank_account_id_seq',
    GREATEST((SELECT COALESCE(MAX(bank_account_id), 0) FROM legal.bank_account), 1),
    true
)
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_account
    ALTER COLUMN bank_account_id SET DEFAULT nextval('legal.bank_account_bank_account_id_seq'),
    ALTER COLUMN bank_account_id SET NOT NULL
SQL);

        DB::statement(<<<'SQL'
ALTER SEQUENCE legal.bank_account_bank_account_id_seq
    OWNED BY legal.bank_account.bank_account_id
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'bank_account_bank_account_id_key'
    ) THEN
        ALTER TABLE legal.bank_account
            ADD CONSTRAINT bank_account_bank_account_id_key UNIQUE (bank_account_id);
    END IF;
END
$$
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_account
    DROP CONSTRAINT IF EXISTS bank_account_bank_account_id_key
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_account
    DROP COLUMN IF EXISTS bank_account_id
SQL);

        DB::statement(<<<'SQL'
DROP SEQUENCE IF EXISTS legal.bank_account_bank_account_id_seq
SQL);
    }
};
