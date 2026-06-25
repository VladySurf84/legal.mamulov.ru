<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE legal.kassa ADD COLUMN IF NOT EXISTS cash_entry_id bigint');
        DB::statement('CREATE INDEX IF NOT EXISTS kassa_cash_entry_id_idx ON legal.kassa (cash_entry_id)');

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'kassa_cash_entry_id_fkey'
            AND conrelid = 'legal.kassa'::regclass
    ) THEN
        ALTER TABLE legal.kassa
            ADD CONSTRAINT kassa_cash_entry_id_fkey
            FOREIGN KEY (cash_entry_id)
            REFERENCES legal.cash_entries(cash_entry_id)
            ON DELETE SET NULL;
    END IF;
END $$;
SQL);

        $this->updateKassaCashEntryLinks();
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE legal.kassa DROP CONSTRAINT IF EXISTS kassa_cash_entry_id_fkey');
        DB::statement('DROP INDEX IF EXISTS legal.kassa_cash_entry_id_idx');
        DB::statement('ALTER TABLE legal.kassa DROP COLUMN IF EXISTS cash_entry_id');
    }

    private function updateKassaCashEntryLinks(): void
    {
        DB::statement(<<<'SQL'
UPDATE legal.kassa k
SET cash_entry_id = (
    SELECT ce.cash_entry_id
    FROM legal.cash_entries ce
    WHERE ce.source_type = 'bank_rule'
        AND ce.amount > 0
        AND ce.metadata->>'contractor_inn' IN ('7704217370', '9714053621', '7721546864')
        AND date_trunc('second', ce.occurred_at) = date_trunc('second', k."time")
        AND ce.amount BETWEEN k.amount - 1 AND k.amount + 1
    ORDER BY abs(ce.amount - k.amount), ce.cash_entry_id
    LIMIT 1
)
WHERE k.reconciliation_id IS NOT NULL
    AND k.description ILIKE 'Выплата%'
SQL);
    }
};
