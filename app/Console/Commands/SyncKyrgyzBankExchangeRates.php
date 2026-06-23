<?php

namespace App\Console\Commands;

use App\Services\ExchangeRates\KyrgyzBankExchangeRateSyncService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SyncKyrgyzBankExchangeRates extends Command
{
    protected $signature = 'exchange-rates:sync-kgs-banks
        {--provider=* : Provider to sync: mbank, obank}
        {--started-by-type=console : Run initiator type: system, user, console}
        {--started-by-user-id= : User id when started-by-type=user}
        {--started-from=cli : Run source label: scheduler, ui, cli}';

    protected $description = 'Sync MBank and Obank exchange rates with observed validity intervals.';

    public function handle(KyrgyzBankExchangeRateSyncService $service): int
    {
        try {
            $summary = $service->sync(
                $this->providers(),
                $this->runContext(),
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'KGS exchange rates sync complete: run #%d, providers %d, quotes %d, opened %d, updated %d, closed %d',
            $summary['sync_run_id'],
            $summary['providers'],
            $summary['quotes'],
            $summary['intervals_opened'],
            $summary['intervals_updated'],
            $summary['intervals_closed'],
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function providers(): array
    {
        $providers = array_values(array_filter(array_map(
            fn ($provider) => strtolower(trim((string) $provider)),
            (array) $this->option('provider'),
        )));

        return $providers === [] ? ['mbank', 'obank'] : $providers;
    }

    /**
     * @return array{started_by_type: string, started_by_user_id: int|null, started_from: string}
     */
    private function runContext(): array
    {
        $type = (string) ($this->option('started-by-type') ?: 'console');

        if (! in_array($type, ['system', 'user', 'console'], true)) {
            throw new InvalidArgumentException('The --started-by-type option must be system, user, or console.');
        }

        $userId = $this->option('started-by-user-id');

        return [
            'started_by_type' => $type,
            'started_by_user_id' => $userId === null || $userId === '' ? null : (int) $userId,
            'started_from' => (string) ($this->option('started-from') ?: 'cli'),
        ];
    }
}
