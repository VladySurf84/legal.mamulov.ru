<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('legal.user_access_scopes')
            ->where('scope_type', 'bank_account')
            ->delete();

        DB::statement('ALTER TABLE legal.user_access_scopes DROP CONSTRAINT IF EXISTS user_access_scopes_scope_type_check');
        DB::statement('ALTER TABLE legal.user_access_scopes DROP CONSTRAINT IF EXISTS user_access_scopes_scope_id_check');

        DB::statement(<<<'SQL'
ALTER TABLE legal.user_access_scopes
ADD CONSTRAINT user_access_scopes_scope_type_check
CHECK (scope_type IN ('legal'))
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE legal.user_access_scopes
ADD CONSTRAINT user_access_scopes_scope_id_check
CHECK (scope_type = 'legal' AND scope_id IS NOT NULL AND scope_id ~ '^[0-9]{10,12}$')
SQL);
    }

    public function down(): void
    {
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
};
