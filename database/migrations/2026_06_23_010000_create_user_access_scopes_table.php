<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.user_access_scopes (
    user_access_scope_id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES legal.laravel_users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    scope_type varchar(50) NOT NULL,
    scope_id varchar(64),
    can_view boolean NOT NULL DEFAULT false,
    can_import_bank_statements boolean NOT NULL DEFAULT false,
    can_sync_bank_api boolean NOT NULL DEFAULT false,
    can_manage_api_credentials boolean NOT NULL DEFAULT false,
    can_edit_manual_operations boolean NOT NULL DEFAULT false,
    can_manage_reference_data boolean NOT NULL DEFAULT false,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT user_access_scopes_scope_type_check
        CHECK (scope_type IN ('all_graph', 'legal')),
    CONSTRAINT user_access_scopes_scope_id_check
        CHECK (
            (scope_type = 'all_graph' AND scope_id IS NULL)
            OR (scope_type = 'legal' AND scope_id IS NOT NULL AND scope_id ~ '^[0-9]{10,12}$')
        )
)
SQL);

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS user_access_scopes_user_scope_unique
ON legal.user_access_scopes (
    user_id,
    scope_type,
    COALESCE(scope_id, '')
)
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS user_access_scopes_scope_idx ON legal.user_access_scopes (scope_type, scope_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS legal.user_access_scopes');
    }
};
