<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->dropForeignKeys();

            DB::statement(<<<'SQL'
ALTER TABLE legal.legal_own
    DROP CONSTRAINT IF EXISTS legal_pkey,
    DROP CONSTRAINT IF EXISTS legal_own_pkey
SQL);

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

            DB::statement(<<<'SQL'
ALTER TABLE legal.legal_own
    ADD CONSTRAINT legal_own_pkey PRIMARY KEY (legal_id)
SQL);

            $this->addForeignKeys();
        });
    }

    public function down(): void
    {
        // INN identifiers may have leading zeroes, so converting them back to numbers would be lossy.
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
