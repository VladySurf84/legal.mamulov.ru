<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE legal.laravel_users
    ADD COLUMN IF NOT EXISTS telegram_chat_id varchar(50)
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS laravel_users_telegram_chat_id_idx ON legal.laravel_users (telegram_chat_id)');

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.telegram_sent_messages (
    telegram_sent_message_id bigserial PRIMARY KEY,
    user_id bigint REFERENCES legal.laravel_users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    telegram_chat_id varchar(50) NOT NULL,
    message text NOT NULL,
    parse_mode varchar(20) NOT NULL DEFAULT 'HTML',
    disable_web_page_preview boolean NOT NULL DEFAULT false,
    http_code integer,
    response_body text,
    error_message text,
    sent_at timestamp(0) without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
)
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS telegram_sent_messages_user_idx ON legal.telegram_sent_messages (user_id, sent_at DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS telegram_sent_messages_chat_idx ON legal.telegram_sent_messages (telegram_chat_id, sent_at DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS legal.telegram_sent_messages_chat_idx');
        DB::statement('DROP INDEX IF EXISTS legal.telegram_sent_messages_user_idx');
        DB::statement('DROP TABLE IF EXISTS legal.telegram_sent_messages');
        DB::statement('DROP INDEX IF EXISTS legal.laravel_users_telegram_chat_id_idx');
        DB::statement('ALTER TABLE legal.laravel_users DROP COLUMN IF EXISTS telegram_chat_id');
    }
};
