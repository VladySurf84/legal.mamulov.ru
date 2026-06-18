<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_account
    DROP CONSTRAINT IF EXISTS bank_account_bank_account_id_key
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'bank_account_account_number_bank_id_key'
    ) THEN
        ALTER TABLE legal.bank_account
            ADD CONSTRAINT bank_account_account_number_bank_id_key UNIQUE (account_number, bank_id);
    END IF;
END
$$
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_transaction
    DROP CONSTRAINT IF EXISTS bank_transaction_ibfk_1
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_account
    DROP CONSTRAINT IF EXISTS bank_account_pkey
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_account
    ADD CONSTRAINT bank_account_pkey PRIMARY KEY (bank_account_id)
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_transaction
    ADD CONSTRAINT bank_transaction_ibfk_1
    FOREIGN KEY (account_number, bank_id)
    REFERENCES legal.bank_account(account_number, bank_id)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_transaction
    DROP CONSTRAINT IF EXISTS bank_transaction_ibfk_1
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_account
    DROP CONSTRAINT IF EXISTS bank_account_pkey
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_account
    ADD CONSTRAINT bank_account_pkey PRIMARY KEY (account_number, bank_id)
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_transaction
    ADD CONSTRAINT bank_transaction_ibfk_1
    FOREIGN KEY (account_number, bank_id)
    REFERENCES legal.bank_account(account_number, bank_id)
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.bank_account
    DROP CONSTRAINT IF EXISTS bank_account_account_number_bank_id_key
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
};
