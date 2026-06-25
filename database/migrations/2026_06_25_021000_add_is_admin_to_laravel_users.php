<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE legal.laravel_users ADD COLUMN IF NOT EXISTS is_admin boolean NOT NULL DEFAULT false');
        DB::statement("UPDATE legal.laravel_users SET is_admin = true WHERE lower(email) = 'ecomicron@gmail.com'");
        DB::statement('CREATE INDEX IF NOT EXISTS laravel_users_is_admin_idx ON legal.laravel_users (is_admin)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS legal.laravel_users_is_admin_idx');
        DB::statement('ALTER TABLE legal.laravel_users DROP COLUMN IF EXISTS is_admin');
    }
};
