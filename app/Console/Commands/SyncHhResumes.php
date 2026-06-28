<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Hh\HhResumeSyncService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SyncHhResumes extends Command
{
    protected $signature = 'hh:sync-responses
        {vacancy_id : HH vacancy id}
        {--user-id= : User id whose HH OAuth token should be used}
        {--started-by-type=console : Run initiator type: system, user, console}
        {--started-by-user-id= : User id when started-by-type=user}
        {--started-from=cli : Run source label: scheduler, ui, cli}';

    protected $description = 'Sync HH vacancy responses and resume data.';

    public function handle(HhResumeSyncService $service): int
    {
        try {
            $user = $this->tokenOwner();
            $summary = $service->sync((string) $this->argument('vacancy_id'), $user, $this->runContext());
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->info(sprintf(
            'HH responses sync complete: run #%d, vacancy %s, %d collection(s), %d response(s), %d PDF(s).',
            $summary['sync_run_id'],
            $summary['vacancy_id'],
            $summary['collections'],
            $summary['negotiations'],
            $summary['pdfs'],
        ));

        return self::SUCCESS;
    }

    private function tokenOwner(): User
    {
        $userId = $this->option('user-id') ?: $this->option('started-by-user-id');

        if ($userId === null || $userId === '') {
            $user = User::query()
                ->where('is_admin', true)
                ->orderBy('id')
                ->first();
        } else {
            $user = User::query()->find((int) $userId);
        }

        if (! $user instanceof User) {
            throw new InvalidArgumentException('Cannot find user for HH OAuth token.');
        }

        return $user;
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
