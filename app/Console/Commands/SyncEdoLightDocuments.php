<?php

namespace App\Console\Commands;

use App\Services\EdoLight\EdoLightClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncEdoLightDocuments extends Command
{
    protected $signature = 'edo-light:sync-documents
        {legal_id? : legal.legal_own.legal_id/ИНН to sync}
        {--legal-name=Рыбников : legal.legal_own.legal_name search fragment when legal_id is omitted}
        {--direction=all : all, incoming or outgoing}
        {--limit=100 : EDO Light list limit per direction}
        {--offset=0 : EDO Light list offset}
        {--content-limit=5 : How many document XML contents to download; use -1 for all, 0 for list only}';

    protected $description = 'Sync EDO Light document list and selected document XML contents into source records.';

    public function handle(EdoLightClient $client): int
    {
        $legalId = $this->resolveLegalId();
        $limit = max(1, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $contentLimitOption = (int) $this->option('content-limit');
        $contentLimit = $contentLimitOption < 0 ? null : $contentLimitOption;
        $direction = (string) $this->option('direction');
        $runId = $this->createRun($legalId);

        try {
            $summary = $client->syncDocuments($legalId, $runId, $limit, $offset, $contentLimit, $direction);
            $this->finishRun($runId, 'success', $summary);

            $this->info(sprintf(
                'EDO Light documents sync complete: run #%d, legal %s, documents %d, contents %d.',
                $runId,
                $legalId,
                $summary['documents'],
                $summary['content_downloaded'],
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->finishRun($runId, 'failed', ['documents' => 0, 'content_downloaded' => 0], $exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveLegalId(): string
    {
        $legalId = $this->argument('legal_id');

        if ($legalId !== null && trim((string) $legalId) !== '') {
            return trim((string) $legalId);
        }

        $name = trim((string) $this->option('legal-name'));

        if ($name === '') {
            throw new RuntimeException('Pass legal_id or --legal-name.');
        }

        $matches = DB::table('legal.legal_own')
            ->where('legal_name', 'ILIKE', '%'.$name.'%')
            ->orWhere('legal_fullname', 'ILIKE', '%'.$name.'%')
            ->orWhere('legal_inn', $name)
            ->orderBy('legal_name')
            ->get(['legal_id', 'legal_name', 'legal_inn']);

        if ($matches->isEmpty()) {
            throw new RuntimeException("Legal entity matching {$name} was not found.");
        }

        if ($matches->count() > 1) {
            $this->table(['legal_id', 'legal_name', 'legal_inn'], $matches->map(fn ($row): array => [
                (string) $row->legal_id,
                (string) $row->legal_name,
                (string) $row->legal_inn,
            ])->all());

            throw new RuntimeException("Several legal entities match {$name}. Pass legal_id explicitly.");
        }

        return (string) $matches->first()->legal_id;
    }

    private function createRun(string $legalId): int
    {
        $now = now();

        return (int) DB::table('legal.api_sync_runs')->insertGetId([
            'provider' => 'edo_light',
            'type' => 'documents_sync',
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

    /**
     * @param  array{documents: int, content_downloaded: int}  $summary
     */
    private function finishRun(int $runId, string $status, array $summary, ?Throwable $exception = null): void
    {
        DB::table('legal.api_sync_runs')
            ->where('api_sync_run_id', $runId)
            ->update([
                'status' => $status,
                'accounts_count' => 1,
                'operations_count' => $summary['documents'],
                'requests_count' => DB::table('legal.api_sync_requests')->where('api_sync_run_id', $runId)->count(),
                'error' => $exception?->getMessage(),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
