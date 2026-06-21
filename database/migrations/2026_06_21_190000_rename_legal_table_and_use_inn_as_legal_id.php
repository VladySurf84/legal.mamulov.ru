<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->renameTable();
            $this->assertInnCanBePrimaryKey();
            $this->createIdMap();
            $this->dropForeignKeys();
            $this->dropPrimaryKey();
            $this->convertColumnsToString();
            $this->rewriteOwnLegalIds();
            $this->rewriteReferencingIds();
            $this->addPrimaryKey();
            $this->addForeignKeys();
        });
    }

    public function down(): void
    {
        // The old surrogate ids are intentionally discarded.
    }

    private function renameTable(): void
    {
        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF to_regclass('legal.legal_own') IS NULL AND to_regclass('legal.legal') IS NOT NULL THEN
        ALTER TABLE legal.legal RENAME TO legal_own;
    END IF;
END $$;
SQL);
    }

    private function assertInnCanBePrimaryKey(): void
    {
        $invalid = DB::selectOne(<<<'SQL'
SELECT count(*) AS count
FROM legal.legal_own
WHERE legal_inn IS NULL
SQL);

        if ((int) $invalid->count > 0) {
            throw new RuntimeException('Cannot use legal_inn as legal_id: legal.legal_own contains rows without legal_inn.');
        }

        $duplicates = DB::selectOne(<<<'SQL'
SELECT count(*) AS count
FROM (
    SELECT legal_inn
    FROM legal.legal_own
    GROUP BY legal_inn
    HAVING count(*) > 1
) duplicate_inn
SQL);

        if ((int) $duplicates->count > 0) {
            throw new RuntimeException('Cannot use legal_inn as legal_id: legal.legal_own contains duplicate legal_inn values.');
        }
    }

    private function createIdMap(): void
    {
        DB::statement(<<<'SQL'
CREATE TEMP TABLE legal_own_id_map ON COMMIT DROP AS
SELECT
    legal_id::text AS old_legal_id,
    legal_inn::text AS new_legal_id
FROM legal.legal_own
SQL);
    }

    private function dropForeignKeys(): void
    {
        foreach ($this->foreignKeys() as [$table, $constraint]) {
            DB::statement(sprintf(
                'ALTER TABLE %s DROP CONSTRAINT IF EXISTS "%s"',
                $table,
                $constraint
            ));
        }
    }

    private function dropPrimaryKey(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.legal_own
    DROP CONSTRAINT IF EXISTS legal_pkey,
    DROP CONSTRAINT IF EXISTS legal_own_pkey
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.legal_own
    ALTER COLUMN legal_id DROP IDENTITY IF EXISTS,
    ALTER COLUMN legal_id DROP DEFAULT
SQL);
    }

    private function convertColumnsToString(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.legal_own
    ALTER COLUMN legal_id TYPE varchar(12) USING legal_id::text,
    ALTER COLUMN legal_inn TYPE varchar(12) USING legal_inn::text
SQL);

        foreach ($this->legalIdTables() as $table) {
            DB::statement(sprintf(
                'ALTER TABLE %s ALTER COLUMN legal_id DROP IDENTITY IF EXISTS',
                $table
            ));

            DB::statement(sprintf(
                'ALTER TABLE %s ALTER COLUMN legal_id TYPE varchar(12) USING legal_id::text',
                $table
            ));
        }

        DB::statement(<<<'SQL'
ALTER TABLE legal.legal_brand
    ALTER COLUMN legal_id_owner TYPE varchar(12) USING legal_id_owner::text
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.api_credentials
    ALTER COLUMN owner_id TYPE varchar(100) USING owner_id::text
SQL);
    }

    private function rewriteOwnLegalIds(): void
    {
        DB::statement(<<<'SQL'
UPDATE legal.legal_own own
SET legal_id = map.new_legal_id
FROM legal_own_id_map map
WHERE own.legal_id::text = map.old_legal_id
SQL);
    }

    private function rewriteReferencingIds(): void
    {
        foreach ($this->legalIdTables() as $table) {
            DB::statement(sprintf(
                'UPDATE %s target SET legal_id = map.new_legal_id FROM legal_own_id_map map WHERE target.legal_id::text = map.old_legal_id',
                $table
            ));
        }

        DB::statement(<<<'SQL'
UPDATE legal.legal_brand target
SET legal_id_owner = map.new_legal_id
FROM legal_own_id_map map
WHERE target.legal_id_owner::text = map.old_legal_id
SQL);

        DB::statement(<<<'SQL'
UPDATE legal.api_credentials target
SET owner_id = map.new_legal_id,
    updated_at = now()
FROM legal_own_id_map map
WHERE target.owner_type = 'legal'
    AND target.owner_id::text = map.old_legal_id
SQL);
    }

    private function addPrimaryKey(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.legal_own
    ADD CONSTRAINT legal_own_pkey PRIMARY KEY (legal_id)
SQL);
    }

    private function addForeignKeys(): void
    {
        foreach ($this->foreignKeys() as [$table, $constraint, $column, $onDelete]) {
            DB::statement(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT "%s" FOREIGN KEY (%s) REFERENCES legal.legal_own(legal_id) ON UPDATE CASCADE ON DELETE %s',
                $table,
                $constraint,
                $column,
                $onDelete
            ));
        }
    }

    /**
     * @return array<int, string>
     */
    private function legalIdTables(): array
    {
        return [
            'legal.bank_account',
            'legal.legal_tax',
            'legal.legal_tax_period',
            'legal.vat_book_imports',
            'legal.vat_book_entries',
            'legal.vat_events',
            'legal.counterparty_opening_balances',
            'legal.source_record_vat_book_details',
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private function foreignKeys(): array
    {
        return [
            ['legal.bank_account', 'bank_account_ibfk_2', 'legal_id', 'RESTRICT'],
            ['legal.legal_brand', 'legal_brand_ibfk_1', 'legal_id_owner', 'RESTRICT'],
            ['legal.legal_tax', 'legal_tax_ibfk_1', 'legal_id', 'RESTRICT'],
            ['legal.legal_tax_period', 'legal_tax_period_ibfk_1', 'legal_id', 'RESTRICT'],
            ['legal.vat_book_imports', 'vat_book_imports_legal_id_fkey', 'legal_id', 'RESTRICT'],
            ['legal.vat_book_entries', 'vat_book_entries_legal_id_fkey', 'legal_id', 'RESTRICT'],
            ['legal.vat_events', 'vat_events_legal_id_fkey', 'legal_id', 'RESTRICT'],
            ['legal.counterparty_opening_balances', 'counterparty_opening_balances_legal_id_fkey', 'legal_id', 'RESTRICT'],
            ['legal.source_record_vat_book_details', 'source_record_vat_book_details_legal_fkey', 'legal_id', 'SET NULL'],
        ];
    }
};
