<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.laravel_user_ui_settings (
    user_ui_setting_id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES legal.laravel_users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    setting_key varchar(191) NOT NULL,
    settings jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT laravel_user_ui_settings_settings_object_check
        CHECK (jsonb_typeof(settings) = 'object')
)
SQL);

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS laravel_user_ui_settings_user_key_unique
ON legal.laravel_user_ui_settings (user_id, setting_key)
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS legal.laravel_user_ui_settings');
    }
};
