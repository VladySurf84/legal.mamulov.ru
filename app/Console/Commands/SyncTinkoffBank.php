<?php

namespace App\Console\Commands;

use App\Services\Bank\TinkoffBankSyncService;
use Illuminate\Console\Command;

class SyncTinkoffBank extends Command
{
    protected $signature = 'tinkoff:sync-bank {--days= : Number of recent days to sync}';

    protected $description = 'Sync Tinkoff bank accounts and recent bank statement operations.';

    public function handle(TinkoffBankSyncService $service): int
    {
        $days = (int) ($this->option('days') ?: config('bank.tinkoff.sync_days'));
        $summary = $service->sync($days);

        $this->info(sprintf(
            'Tinkoff sync complete: run #%d, %d legal(s), %d credential(s), %d account(s), %d operation(s), period %s..%s',
            $summary['sync_run_id'],
            $summary['legals'],
            $summary['credentials'],
            $summary['accounts'],
            $summary['operations'],
            $summary['from'],
            $summary['till'],
        ));

        return self::SUCCESS;
    }
}
