<?php

namespace App\Http\Controllers;

use App\Console\ScheduleDefinitions;
use App\Support\UserAccess;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SchedulerController extends Controller
{
    public function index(Schedule $schedule): View
    {
        abort_unless(UserAccess::canViewScheduler(request()->user()), 403);

        if ($schedule->events() === []) {
            ScheduleDefinitions::define($schedule);
        }

        $tasks = collect($schedule->events())
            ->map(fn (Event $event): array => $this->taskFromEvent($event))
            ->sortBy('next_run_at')
            ->values();

        return view('scheduler.index', [
            'tasks' => $tasks,
            'canRunScheduler' => UserAccess::canRunScheduler(request()->user()),
        ]);
    }

    public function run(string $task): RedirectResponse
    {
        abort_unless(UserAccess::canRunScheduler(request()->user()), 403);

        $commands = [
            'tinkoff-bank' => [
                'command' => 'tinkoff:sync-bank',
                'parameters' => [
                    '--days' => config('bank.tinkoff.sync_days'),
                    '--started-by-type' => 'user',
                    '--started-by-user-id' => auth()->id(),
                    '--started-from' => 'ui',
                ],
            ],
            'kgs-exchange-rates' => [
                'command' => 'exchange-rates:sync-kgs-banks',
                'parameters' => [
                    '--started-by-type' => 'user',
                    '--started-by-user-id' => auth()->id(),
                    '--started-from' => 'ui',
                ],
            ],
        ];

        if (! isset($commands[$task])) {
            abort(404);
        }

        $exitCode = Artisan::call($commands[$task]['command'], $commands[$task]['parameters']);

        $output = trim(Artisan::output());

        return redirect()
            ->route('scheduler.index')
            ->with(
                $exitCode === 0 ? 'status' : 'error',
                $output !== '' ? $output : 'Задание выполнено.'
            );
    }

    private function taskFromEvent(Event $event): array
    {
        $outputPath = $event->output === $this->defaultOutputPath() ? null : $event->output;
        $nextRunAt = $event->nextRunDate();

        return [
            'command' => $this->displayCommand($event),
            'description' => $event->description,
            'expression' => $event->getExpression(),
            'next_run_at' => $nextRunAt,
            'next_run_label' => $this->formatDate($nextRunAt),
            'next_run_diff' => $nextRunAt->diffForHumans(),
            'timezone' => $event->timezone ?: config('app.display_timezone', config('app.timezone')),
            'without_overlapping' => $event->withoutOverlapping,
            'on_one_server' => $event->onOneServer,
            'output_path' => $outputPath,
            'output_exists' => $outputPath !== null && File::exists($outputPath),
            'output_size' => $this->formatBytes($outputPath),
            'output_updated_at' => $this->outputUpdatedAt($outputPath),
            'runs' => $this->runsForEvent($event),
            'run_route' => $this->runRouteForEvent($event),
        ];
    }

    /**
     * @return array<int, object>
     */
    private function runsForEvent(Event $event): array
    {
        $runFilter = $this->runFilterForEvent($event);

        if ($runFilter === null) {
            return [];
        }

        $runs = DB::table('legal.api_sync_runs as runs')
            ->leftJoin('legal.laravel_users as users', 'users.id', '=', 'runs.started_by_user_id')
            ->where('provider', $runFilter['provider'])
            ->where('type', $runFilter['type'])
            ->orderByDesc('runs.started_at')
            ->limit(10)
            ->get([
                'runs.*',
                'users.name as started_by_user_name',
                'users.email as started_by_user_email',
            ]);

        if ($runs->isEmpty()) {
            return [];
        }

        $requests = DB::table('legal.api_sync_requests')
            ->whereIn('api_sync_run_id', $runs->pluck('api_sync_run_id'))
            ->orderBy('api_sync_request_id')
            ->get()
            ->groupBy('api_sync_run_id');

        return $runs
            ->map(function (object $run) use ($requests): object {
                $run->requests = $requests->get($run->api_sync_run_id, collect())->all();
                $run->started_at_label = $this->formatNullableDate($run->started_at);
                $run->finished_at_label = $this->formatNullableDate($run->finished_at);
                $run->started_by_label = $this->startedByLabel($run);

                return $run;
            })
            ->all();
    }

    private function runRouteForEvent(Event $event): ?string
    {
        $command = (string) $event->command;

        if (str_contains($command, 'tinkoff:sync-bank')) {
            return route('scheduler.run', ['task' => 'tinkoff-bank']);
        }

        if (str_contains($command, 'exchange-rates:sync-kgs-banks')) {
            return route('scheduler.run', ['task' => 'kgs-exchange-rates']);
        }

        return null;
    }

    /**
     * @return array{provider: string, type: string}|null
     */
    private function runFilterForEvent(Event $event): ?array
    {
        $command = (string) $event->command;

        if (str_contains($command, 'tinkoff:sync-bank')) {
            return ['provider' => 'tinkoff', 'type' => 'bank_sync'];
        }

        if (str_contains($command, 'exchange-rates:sync-kgs-banks')) {
            return ['provider' => 'kgs_exchange_rates', 'type' => 'exchange_rates_sync'];
        }

        return null;
    }

    private function startedByLabel(object $run): string
    {
        return match ($run->started_by_type ?? 'console') {
            'user' => ($run->started_by_user_name ?: $run->started_by_user_email ?: 'пользователь').' · '.($run->started_from ?: 'ui'),
            'system' => 'system · '.($run->started_from ?: 'scheduler'),
            default => 'console · '.($run->started_from ?: 'cli'),
        };
    }

    private function displayCommand(Event $event): string
    {
        $command = $event->command ?? $event->getSummaryForDisplay();
        $artisan = ConsoleApplication::formatCommandString('');

        return trim(str_replace($artisan, 'php artisan', $command));
    }

    private function defaultOutputPath(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    }

    private function formatDate(Carbon $date): string
    {
        return $date
            ->timezone($this->displayTimezone())
            ->format('d.m.Y H:i');
    }

    private function formatBytes(?string $path): ?string
    {
        if ($path === null || ! File::exists($path)) {
            return null;
        }

        $bytes = File::size($path);

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', ' ').' KB';
        }

        return number_format($bytes / 1024 / 1024, 1, ',', ' ').' MB';
    }

    private function outputUpdatedAt(?string $path): ?string
    {
        if ($path === null || ! File::exists($path)) {
            return null;
        }

        return Carbon::createFromTimestamp(File::lastModified($path))
            ->timezone($this->displayTimezone())
            ->format('d.m.Y H:i');
    }

    private function formatNullableDate(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return Carbon::parse((string) $date, 'UTC')
            ->timezone($this->displayTimezone())
            ->format('d.m.Y H:i:s');
    }

    private function displayTimezone(): string
    {
        return (string) config('app.display_timezone', config('app.timezone'));
    }
}
