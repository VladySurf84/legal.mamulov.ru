<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS legal');

        if ($this->tableExists('public', 'migrations') && ! $this->tableExists('legal', 'laravel_migrations')) {
            DB::statement('ALTER TABLE public.migrations SET SCHEMA legal');
            DB::statement('ALTER TABLE legal.migrations RENAME TO laravel_migrations');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if ($this->tableExists('legal', 'laravel_migrations') && ! $this->tableExists('public', 'migrations')) {
            DB::statement('ALTER TABLE legal.laravel_migrations RENAME TO migrations');
            DB::statement('ALTER TABLE legal.migrations SET SCHEMA public');
        }
    }

    private function tableExists(string $schema, string $table): bool
    {
        return (bool) DB::table('information_schema.tables')
            ->where('table_schema', $schema)
            ->where('table_name', $table)
            ->exists();
    }
};
