<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $renames = [
        'users' => 'laravel_users',
        'sessions' => 'laravel_sessions',
        'cache' => 'laravel_cache',
        'cache_locks' => 'laravel_cache_locks',
        'jobs' => 'laravel_jobs',
        'job_batches' => 'laravel_job_batches',
        'failed_jobs' => 'laravel_failed_jobs',
        'password_reset_tokens' => 'laravel_password_reset_tokens',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->renames as $from => $to) {
            if ($this->tableExists('legal', $from) && ! $this->tableExists('legal', $to)) {
                DB::statement(sprintf(
                    'ALTER TABLE legal.%s RENAME TO %s',
                    $this->quoteIdentifier($from),
                    $this->quoteIdentifier($to),
                ));
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse($this->renames) as $from => $to) {
            if ($this->tableExists('legal', $to) && ! $this->tableExists('legal', $from)) {
                DB::statement(sprintf(
                    'ALTER TABLE legal.%s RENAME TO %s',
                    $this->quoteIdentifier($to),
                    $this->quoteIdentifier($from),
                ));
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
