<?php

namespace App\Console\Commands;

use App\Services\EdoLight\EdoLightClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AuthEdoLight extends Command
{
    protected $signature = 'edo-light:auth
        {legal_id : legal.legal_own.legal_id to authenticate as}
        {--show-token : Print token prefix for manual diagnostics}';

    protected $description = 'Authenticate in EDO Light through True API and CryptoPro certificate.';

    public function handle(EdoLightClient $client): int
    {
        $legalId = (string) $this->argument('legal_id');
        $runId = $this->createRun($legalId);

        try {
            $payload = $client->authenticate($legalId, $runId);
            $this->finishRun($runId, 'success');

            $this->info("EDO Light auth complete: run #{$runId}, legal #{$legalId}.");

            if ($this->option('show-token')) {
                $token = (string) ($payload['token'] ?? '');
                $this->line('Token prefix: '.substr($token, 0, 16).'...');
            }

            if (! empty($payload['expireDate'])) {
                $this->line('Expires: '.$payload['expireDate']);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->finishRun($runId, 'failed', $exception->getMessage());
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function createRun(string $legalId): int
    {
        $now = now();

        return (int) DB::table('legal.api_sync_runs')->insertGetId([
            'provider' => 'edo_light',
            'type' => 'auth',
            'status' => 'started',
            'accounts_count' => 1,
            'operations_count' => 0,
            'requests_count' => 0,
            'started_by_type' => 'console',
            'started_from' => 'cli',
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'error' => "legal_id={$legalId}",
        ], 'api_sync_run_id');
    }

    private function finishRun(int $runId, string $status, ?string $error = null): void
    {
        DB::table('legal.api_sync_runs')
            ->where('api_sync_run_id', $runId)
            ->update([
                'status' => $status,
                'requests_count' => DB::table('legal.api_sync_requests')->where('api_sync_run_id', $runId)->count(),
                'error' => $error,
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
