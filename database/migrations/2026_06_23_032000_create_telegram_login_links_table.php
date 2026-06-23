<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS laravel_users_telegram_chat_id_unique ON legal.laravel_users (telegram_chat_id) WHERE telegram_chat_id IS NOT NULL');

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.telegram_login_links (
    telegram_login_link_id bigserial PRIMARY KEY,
    telegram_chat_id varchar(50) NOT NULL REFERENCES legal.telegram_chats(telegram_chat_id) ON UPDATE CASCADE ON DELETE CASCADE,
    token varchar(100) NOT NULL UNIQUE,
    user_id bigint REFERENCES legal.laravel_users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    used_at timestamp(0) without time zone,
    last_sent_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
)
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS telegram_login_links_chat_idx ON legal.telegram_login_links (telegram_chat_id, used_at, expires_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS telegram_login_links_user_idx ON legal.telegram_login_links (user_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS legal.telegram_login_links_user_idx');
        DB::statement('DROP INDEX IF EXISTS legal.telegram_login_links_chat_idx');
        DB::statement('DROP TABLE IF EXISTS legal.telegram_login_links');
        DB::statement('DROP INDEX IF EXISTS legal.laravel_users_telegram_chat_id_unique');
    }
};
