<?php

namespace App\Console\Commands;

use App\Services\Bank\TinkoffBankSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SyncTinkoffBank extends Command
{
    protected $signature = 'tinkoff:sync-bank
        {--days= : Number of recent days to sync}
        {--from= : Statement period start date, YYYY-MM-DD}
        {--till= : Statement period end date, YYYY-MM-DD}
        {--account= : Sync only one bank account number}
        {--chunk-days=30 : Split statement requests into chunks of N days}';

    protected $description = 'Sync Tinkoff bank accounts and recent bank statement operations.';

    public function handle(TinkoffBankSyncService $service): int
    {
        try {
            $summary = $this->hasPeriodOptions()
                ? $service->syncPeriod(
                    $this->dateOption('from'),
                    $this->dateOption('till') ?: now()->toDateString(),
                    $this->accountOption(),
                    $this->chunkDaysOption(),
                )
                : $service->sync(
                    (int) ($this->option('days') ?: config('bank.tinkoff.sync_days')),
                    $this->accountOption(),
                    $this->chunkDaysOption(),
                );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

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

    private function hasPeriodOptions(): bool
    {
        return $this->option('from') !== null || $this->option('till') !== null;
    }

    private function dateOption(string $name): ?string
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            if ($name === 'from' && $this->hasPeriodOptions()) {
                throw new InvalidArgumentException('The --from option is required when syncing a custom period.');
            }

            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', (string) $value)->toDateString();
        } catch (\Throwable) {
            throw new InvalidArgumentException("The --{$name} option must be a valid date in YYYY-MM-DD format.");
        }
    }

    private function accountOption(): ?string
    {
        $account = trim((string) $this->option('account'));

        return $account === '' ? null : $account;
    }

    private function chunkDaysOption(): int
    {
        $chunkDays = (int) $this->option('chunk-days');

        if ($chunkDays < 1) {
            throw new InvalidArgumentException('The --chunk-days option must be greater than zero.');
        }

        return $chunkDays;
    }
}
