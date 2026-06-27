<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS legal.user_module_permissions_user_module_scope_unique');
        DB::statement('ALTER TABLE legal.user_module_permissions DROP CONSTRAINT IF EXISTS user_module_permissions_scope_check');

        DB::statement(<<<'SQL'
ALTER TABLE legal.user_module_permissions
ADD CONSTRAINT user_module_permissions_scope_check
CHECK (
    (scope_type = 'global' AND scope_id IS NULL)
    OR (
        scope_type = 'legal'
        AND scope_id IS NOT NULL
        AND scope_id ~ '^[0-9]{10,12}$'
    )
)
SQL);

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS user_module_permissions_user_module_scope_unique
ON legal.user_module_permissions (
    user_id,
    module,
    scope_type,
    COALESCE(scope_id, '')
)
SQL);
    }

    public function down(): void
    {
        DB::table('legal.user_module_permissions')
            ->where('scope_type', 'global')
            ->delete();

        DB::statement('DROP INDEX IF EXISTS legal.user_module_permissions_user_module_scope_unique');
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
};
