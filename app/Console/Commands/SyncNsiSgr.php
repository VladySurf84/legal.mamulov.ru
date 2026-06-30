<?php

namespace App\Console\Commands;

use App\Services\Nsi\NsiSgrSyncService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SyncNsiSgr extends Command
{
    protected $signature = 'nsi:sgr-sync
        {--mode=list : list, details, or all}
        {--date= : NSI actual date, defaults to today}
        {--limit=1000 : List page size}
        {--start-offset= : Override saved list offset}
        {--max-pages=0 : Stop list sync after N pages, 0 means no page limit}
        {--detail-limit=2000 : Stop detail sync after N cards, 0 means no limit}
        {--refresh-active-after-hours=24 : Legacy option kept for compatible scheduled runs}
        {--number= : Limit detail sync to one SGR number}
        {--pause-ms=300 : Pause between successful requests}
        {--error-pause-ms=10000 : Pause before retry after failed request}
        {--timeout=60 : HTTP request timeout in seconds}
        {--max-retries=5 : Retries per request}
        {--reset : Reset saved list offset for the selected date}
        {--started-by-type=console : Run initiator type: system, user, console}
        {--started-by-user-id= : User id when started-by-type=user}
        {--started-from=cli : Run source label: scheduler, ui, cli}';

    protected $description = 'Sync EAEU NSI SGR registry: first list rows, then optional full detail cards.';

    public function handle(NsiSgrSyncService $service): int
    {
        $mode = strtolower((string) $this->option('mode'));

        if (! in_array($mode, ['list', 'details', 'all'], true)) {
            throw new InvalidArgumentException('The --mode option must be list, details, or all.');
        }

        $options = $this->optionsPayload();

        try {
            if ($mode === 'list' || $mode === 'all') {
                $summary = $service->syncList($options);
                $this->info(sprintf(
                    'NSI SGR list sync complete: run #%d, pages %d, records %d, inserted %d, updated %d, skipped %d, next offset %d of %d.',
                    $summary['sync_run_id'],
                    $summary['pages'],
                    $summary['records'],
                    $summary['inserted'],
                    $summary['updated'],
                    $summary['skipped'],
                    $summary['next_offset'],
                    $summary['total_count'],
                ));
            }

            if ($mode === 'details' || $mode === 'all') {
                $summary = $service->syncDetails($options);
                $this->info(sprintf(
                    'NSI SGR detail sync complete: run #%d, selected %d, loaded %d, failed %d.',
                    $summary['sync_run_id'],
                    $summary['records'],
                    $summary['details'],
                    $summary['failed'],
                ));
            }
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsPayload(): array
    {
        $type = (string) ($this->option('started-by-type') ?: 'console');

        if (! in_array($type, ['system', 'user', 'console'], true)) {
            throw new InvalidArgumentException('The --started-by-type option must be system, user, or console.');
        }

        $userId = $this->option('started-by-user-id');

        return [
            'date' => $this->option('date') ?: now()->toDateString(),
            'limit' => (int) $this->option('limit'),
            'start_offset' => $this->option('start-offset'),
            'max_pages' => (int) $this->option('max-pages'),
            'detail_limit' => (int) $this->option('detail-limit'),
            'refresh_active_after_hours' => (int) $this->option('refresh-active-after-hours'),
            'number' => $this->option('number'),
            'pause_ms' => (int) $this->option('pause-ms'),
            'error_pause_ms' => (int) $this->option('error-pause-ms'),
            'timeout' => (int) $this->option('timeout'),
            'max_retries' => (int) $this->option('max-retries'),
            'reset' => (bool) $this->option('reset'),
            'started_by_type' => $type,
            'started_by_user_id' => $userId === null || $userId === '' ? null : (int) $userId,
            'started_from' => (string) ($this->option('started-from') ?: 'cli'),
        ];
    }
}
