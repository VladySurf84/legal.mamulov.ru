<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.currencies (
    currency_code char(3) PRIMARY KEY,
    alpha_code char(3) NOT NULL UNIQUE,
    minor_units smallint,
    name_ru varchar(255) NOT NULL,
    name_en varchar(255),
    countries text,
    included_on varchar(50),
    source varchar(100) NOT NULL DEFAULT 'okv_wikipedia',
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
)
SQL);

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.currency_aliases (
    currency_alias varchar(10) PRIMARY KEY,
    currency_code char(3) NOT NULL REFERENCES legal.currencies(currency_code)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    alias_type varchar(30) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
)
SQL);

        \App\Support\ReferenceData\CurrencyReferenceData::seed();

        $this->normalizeExistingCurrencyColumns();

        $this->setCurrencyDefaultIfTableExists('legal.source_record_amounts', 'currency', '643');
        $this->setCurrencyDefaultIfTableExists('legal.document_amounts', 'currency', '643');
        $this->setCurrencyDefaultIfTableExists('legal.money_edges', 'currency', '643');
    }

    public function down(): void
    {
        $this->setCurrencyDefaultIfTableExists('legal.source_record_amounts', 'currency', 'RUB');
        $this->setCurrencyDefaultIfTableExists('legal.document_amounts', 'currency', 'RUB');
        $this->setCurrencyDefaultIfTableExists('legal.money_edges', 'currency', 'RUB');

        DB::statement('DROP TABLE IF EXISTS legal.currency_aliases');
        DB::statement('DROP TABLE IF EXISTS legal.currencies');
    }

    private function normalizeExistingCurrencyColumns(): void
    {
        foreach ([
            ['legal.bank_account', 'currency'],
            ['legal.documents', 'currency'],
            ['legal.document_bank_transaction', 'currency'],
            ['legal.money_edges', 'currency'],
            ['legal.document_amounts', 'currency'],
            ['legal.source_record_amounts', 'currency'],
            ['legal.vat_book_entries', 'currency_code'],
        ] as [$table, $column]) {
            if (! $this->tableExists($table)) {
                continue;
            }

            DB::statement(<<<SQL
UPDATE {$table} target
SET {$column} = aliases.currency_code
FROM legal.currency_aliases aliases
WHERE target.{$column} IS NOT NULL
    AND btrim(target.{$column}::text) <> ''
    AND upper(btrim(target.{$column}::text)) = aliases.currency_alias
SQL);
        }
    }

    private function setCurrencyDefaultIfTableExists(string $table, string $column, string $currencyCode): void
    {
        if (! $this->tableExists($table)) {
            return;
        }

        DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT '{$currencyCode}'");
    }

    private function tableExists(string $table): bool
    {
        return DB::selectOne('SELECT to_regclass(?) AS table_name', [$table])->table_name !== null;
    }
};
