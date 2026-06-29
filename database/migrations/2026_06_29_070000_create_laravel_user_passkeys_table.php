<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.laravel_user_passkeys (
    user_passkey_id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES legal.laravel_users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    name varchar(191) NOT NULL,
    credential_id text NOT NULL,
    credential_public_key text NOT NULL,
    signature_count bigint,
    transports jsonb NOT NULL DEFAULT '[]'::jsonb,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT laravel_user_passkeys_transports_array_check
        CHECK (jsonb_typeof(transports) = 'array')
)
SQL);

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS laravel_user_passkeys_credential_unique
ON legal.laravel_user_passkeys (credential_id)
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS laravel_user_passkeys_user_id_index
ON legal.laravel_user_passkeys (user_id)
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS legal.laravel_user_passkeys');
    }
};
