<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.user_module_permissions (
    user_module_permission_id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES legal.laravel_users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    module varchar(64) NOT NULL,
    can_view boolean NOT NULL DEFAULT false,
    can_edit boolean NOT NULL DEFAULT false,
    can_manage boolean NOT NULL DEFAULT false,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT user_module_permissions_module_check
        CHECK (module IN ('kassa', 'scheduler'))
)
SQL);

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS user_module_permissions_user_module_unique
ON legal.user_module_permissions (user_id, module)
SQL);

        DB::statement(<<<'SQL'
INSERT INTO legal.user_module_permissions (
    user_id,
    module,
    can_view,
    can_edit,
    can_manage,
    created_at,
    updated_at
)
SELECT
    user_id,
    'kassa',
    can_view,
    can_edit_manual_operations,
    false,
    NOW(),
    NOW()
FROM legal.user_access_scopes
WHERE scope_type = 'all_graph'
  AND scope_id IS NULL
  AND (can_view OR can_edit_manual_operations)
ON CONFLICT (user_id, module) DO UPDATE SET
    can_view = EXCLUDED.can_view,
    can_edit = EXCLUDED.can_edit,
    updated_at = NOW()
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS legal.user_module_permissions');
    }
};
