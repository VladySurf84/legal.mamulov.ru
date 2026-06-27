<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE legal.user_module_permissions DROP CONSTRAINT IF EXISTS user_module_permissions_module_check');
        DB::statement('DROP INDEX IF EXISTS legal.user_module_permissions_user_module_unique');

        DB::statement(<<<'SQL'
ALTER TABLE legal.user_module_permissions
ADD COLUMN IF NOT EXISTS scope_type varchar(50),
ADD COLUMN IF NOT EXISTS scope_id varchar(64)
SQL);

        DB::table('legal.user_module_permissions')->delete();

        DB::statement(<<<'SQL'
ALTER TABLE legal.user_module_permissions
ADD CONSTRAINT user_module_permissions_module_check
CHECK (module IN (
    'bank_accounts',
    'bank_transactions',
    'counterparties',
    'currencies',
    'document_types',
    'documents',
    'electronic_signatures',
    'exchange_rates',
    'kassa',
    'legal_entities',
    'money_layer',
    'users',
    'vat_book_entries',
    'vat_books',
    'vat_layer'
))
SQL);

        DB::statement('ALTER TABLE legal.user_module_permissions DROP CONSTRAINT IF EXISTS user_module_permissions_scope_check');
        DB::statement(<<<'SQL'
ALTER TABLE legal.user_module_permissions
ADD CONSTRAINT user_module_permissions_scope_check
CHECK (
    scope_type = 'legal'
    AND scope_id IS NOT NULL
    AND scope_id ~ '^[0-9]{10,12}$'
)
SQL);

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS user_module_permissions_user_module_scope_unique
ON legal.user_module_permissions (
    user_id,
    module,
    scope_type,
    scope_id
)
SQL);
    }

    public function down(): void
    {
        DB::table('legal.user_module_permissions')->delete();

        DB::statement('DROP INDEX IF EXISTS legal.user_module_permissions_user_module_scope_unique');
        DB::statement('ALTER TABLE legal.user_module_permissions DROP CONSTRAINT IF EXISTS user_module_permissions_scope_check');
        DB::statement('ALTER TABLE legal.user_module_permissions DROP CONSTRAINT IF EXISTS user_module_permissions_module_check');

        DB::statement(<<<'SQL'
ALTER TABLE legal.user_module_permissions
ADD CONSTRAINT user_module_permissions_module_check
CHECK (module IN ('electronic_signatures', 'kassa', 'users'))
SQL);

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS user_module_permissions_user_module_unique
ON legal.user_module_permissions (user_id, module)
SQL);
    }
};
