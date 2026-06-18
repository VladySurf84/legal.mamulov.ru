<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS legal');

        foreach ($this->tables as $table) {
            if ($this->tableExists('public', $table) && ! $this->tableExists('legal', $table)) {
                DB::statement(sprintf('ALTER TABLE public.%s SET SCHEMA legal', $this->quoteIdentifier($table)));
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse($this->tables) as $table) {
            if ($this->tableExists('legal', $table) && ! $this->tableExists('public', $table)) {
                DB::statement(sprintf('ALTER TABLE legal.%s SET SCHEMA public', $this->quoteIdentifier($table)));
            }
        }
    }

    private function tableExists(string $schema, string $table): bool
    {
        return (bool) DB::table('information_schema.tables')
            ->where('table_schema', $schema)
            ->where('table_name', $table)
            ->exists();
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
