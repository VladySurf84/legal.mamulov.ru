<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
INSERT INTO legal.user_module_permissions (
    user_id,
    module,
    scope_type,
    scope_id,
    can_view,
    created_at,
    updated_at
)
SELECT
    user_id,
    'hh_resumes',
    scope_type,
    scope_id,
    can_view,
    created_at,
    updated_at
FROM legal.user_module_permissions
WHERE module = 'hh_browser_captures'
ON CONFLICT (user_id, module, scope_type, COALESCE(scope_id, '')) DO NOTHING
SQL);

        DB::table('legal.user_module_permissions')
            ->where('module', 'hh_browser_captures')
            ->delete();

        DB::statement('ALTER TABLE legal.user_module_permissions DROP CONSTRAINT IF EXISTS user_module_permissions_module_check');
        DB::statement(<<<'SQL'
ALTER TABLE legal.user_module_permissions
ADD CONSTRAINT user_module_permissions_module_check
CHECK (module IN (
    'bank_accounts',
    'bank_accounts.import',
    'bank_transactions',
    'bank_transactions.import',
    'bank_transactions.sync',
    'counterparties',
    'counterparties.rebuild_links',
    'currencies',
    'document_types',
    'document_types.create',
    'document_types.delete',
    'document_types.edit',
    'documents',
    'electronic_signatures',
    'electronic_signatures.import',
    'exchange_rates',
    'exchange_rates.sync',
    'hh_resumes',
    'kassa',
    'kassa.create',
    'kassa.delete_any',
    'legal_entities',
    'money_layer',
    'money_layer.rebuild',
    'users',
    'vat_book_entries',
    'vat_books',
    'vat_books.import',
    'vat_layer',
    'vat_layer.rebuild',
    'vat_layer.rebuild_bank'
))
SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE legal.user_module_permissions DROP CONSTRAINT IF EXISTS user_module_permissions_module_check');
        DB::statement(<<<'SQL'
ALTER TABLE legal.user_module_permissions
ADD CONSTRAINT user_module_permissions_module_check
CHECK (module IN (
    'bank_accounts',
    'bank_accounts.import',
    'bank_transactions',
    'bank_transactions.import',
    'bank_transactions.sync',
    'counterparties',
    'counterparties.rebuild_links',
    'currencies',
    'document_types',
    'document_types.create',
    'document_types.delete',
    'document_types.edit',
    'documents',
    'electronic_signatures',
    'electronic_signatures.import',
    'exchange_rates',
    'exchange_rates.sync',
    'hh_browser_captures',
    'hh_resumes',
    'kassa',
    'kassa.create',
    'kassa.delete_any',
    'legal_entities',
    'money_layer',
    'money_layer.rebuild',
    'users',
    'vat_book_entries',
    'vat_books',
    'vat_books.import',
    'vat_layer',
    'vat_layer.rebuild',
    'vat_layer.rebuild_bank'
))
SQL);
    }
};
