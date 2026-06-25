<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.bank_operation_types (
    operation_type_code varchar(20) PRIMARY KEY,
    name_ru varchar(255) NOT NULL,
    description text,
    source varchar(100) NOT NULL DEFAULT 'bank_statement',
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
)
SQL);

        DB::statement(<<<'SQL'
INSERT INTO legal.bank_operation_types (
    operation_type_code,
    name_ru,
    description,
    source,
    is_active,
    created_at,
    updated_at
)
VALUES (
    '01',
    'Платежное поручение',
    'Код вида банковской операции в выписке: платежное поручение на перевод денежных средств.',
    '1c_client_bank_exchange',
    true,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
)
ON CONFLICT (operation_type_code) DO UPDATE
SET
    name_ru = EXCLUDED.name_ru,
    description = EXCLUDED.description,
    source = EXCLUDED.source,
    is_active = EXCLUDED.is_active,
    updated_at = CURRENT_TIMESTAMP
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS legal.bank_operation_types');
    }
};
