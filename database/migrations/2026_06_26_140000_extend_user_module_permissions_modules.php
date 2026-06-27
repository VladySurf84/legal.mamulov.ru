<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE legal.user_module_permissions DROP CONSTRAINT IF EXISTS user_module_permissions_module_check');
        DB::statement(<<<'SQL'
ALTER TABLE legal.user_module_permissions
ADD CONSTRAINT user_module_permissions_module_check
CHECK (module IN ('bank_accounts', 'bank_transactions', 'kassa', 'scheduler'))
SQL);

        DB::table('legal.user_access_scopes')
            ->where('scope_type', 'all_graph')
            ->delete();

        DB::statement('ALTER TABLE legal.user_access_scopes DROP CONSTRAINT IF EXISTS user_access_scopes_scope_type_check');
        DB::statement('ALTER TABLE legal.user_access_scopes DROP CONSTRAINT IF EXISTS user_access_scopes_scope_id_check');

        DB::statement(<<<'SQL'
ALTER TABLE legal.user_access_scopes
ADD CONSTRAINT user_access_scopes_scope_type_check
CHECK (scope_type IN ('legal', 'bank_account'))
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.user_access_scopes
ADD CONSTRAINT user_access_scopes_scope_id_check
CHECK (
    (scope_type = 'legal' AND scope_id IS NOT NULL AND scope_id ~ '^[0-9]{10,12}$')
    OR (scope_type = 'bank_account' AND scope_id IS NOT NULL AND scope_id ~ '^[0-9]+$')
)
SQL);
    }

    public function down(): void
    {
        DB::table('legal.user_module_permissions')
            ->whereIn('module', ['bank_accounts', 'bank_transactions'])
            ->delete();

        DB::statement('ALTER TABLE legal.user_module_permissions DROP CONSTRAINT IF EXISTS user_module_permissions_module_check');
        DB::statement(<<<'SQL'
ALTER TABLE legal.user_module_permissions
ADD CONSTRAINT user_module_permissions_module_check
CHECK (module IN ('kassa', 'scheduler'))
SQL);

        DB::statement('ALTER TABLE legal.user_access_scopes DROP CONSTRAINT IF EXISTS user_access_scopes_scope_type_check');
        DB::statement('ALTER TABLE legal.user_access_scopes DROP CONSTRAINT IF EXISTS user_access_scopes_scope_id_check');

        DB::statement(<<<'SQL'
ALTER TABLE legal.user_access_scopes
ADD CONSTRAINT user_access_scopes_scope_type_check
CHECK (scope_type IN ('all_graph', 'legal', 'bank_account'))
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.user_access_scopes
ADD CONSTRAINT user_access_scopes_scope_id_check
CHECK (
    (scope_type = 'all_graph' AND scope_id IS NULL)
    OR (scope_type = 'legal' AND scope_id IS NOT NULL AND scope_id ~ '^[0-9]{10,12}$')
    OR (scope_type = 'bank_account' AND scope_id IS NOT NULL AND scope_id ~ '^[0-9]+$')
)
SQL);
    }
};
