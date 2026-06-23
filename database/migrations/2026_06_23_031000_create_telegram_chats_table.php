<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.telegram_chats (
    telegram_chat_id varchar(50) PRIMARY KEY,
    telegram_user_id varchar(50),
    type varchar(30),
    username varchar(255),
    first_name varchar(255),
    last_name varchar(255),
    title varchar(255),
    last_update_id bigint,
    last_message_text text,
    last_seen_at timestamp(0) without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active boolean NOT NULL DEFAULT true,
    raw_chat jsonb,
    raw_from jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
)
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS telegram_chats_last_seen_idx ON legal.telegram_chats (last_seen_at DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS telegram_chats_username_idx ON legal.telegram_chats (username)');

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.telegram_updates (
    telegram_update_id bigint PRIMARY KEY,
    telegram_chat_id varchar(50) REFERENCES legal.telegram_chats(telegram_chat_id) ON UPDATE CASCADE ON DELETE SET NULL,
    message_text text,
    update_type varchar(50),
    payload jsonb NOT NULL,
    received_at timestamp(0) without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
)
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS telegram_updates_chat_idx ON legal.telegram_updates (telegram_chat_id, telegram_update_id DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS legal.telegram_updates_chat_idx');
        DB::statement('DROP TABLE IF EXISTS legal.telegram_updates');
        DB::statement('DROP INDEX IF EXISTS legal.telegram_chats_username_idx');
        DB::statement('DROP INDEX IF EXISTS legal.telegram_chats_last_seen_idx');
        DB::statement('DROP TABLE IF EXISTS legal.telegram_chats');
    }
};
