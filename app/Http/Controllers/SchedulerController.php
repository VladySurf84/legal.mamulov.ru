<?php

namespace App\Http\Controllers;

use App\Console\ScheduleDefinitions;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Http\RedirectResponse;

class SchedulerController extends Controller
{
    public function index(Schedule $schedule): View
    {
        if ($schedule->events() === []) {
            ScheduleDefinitions::define($schedule);
        }

        $tasks = collect($schedule->events())
            ->map(fn (Event $event): array => $this->taskFromEvent($event))
            ->sortBy('next_run_at')
            ->values();

        return view('scheduler.index', [
            'tasks' => $tasks,
        ]);
    }

    public function run(string $task): RedirectResponse
    {
        if ($task !== 'tinkoff-bank') {
            abort(404);
        }

        $exitCode = Artisan::call('tinkoff:sync-bank', [
            '--days' => config('bank.tinkoff.sync_days'),
        ]);

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
            'timezone' => $event->timezone ?: config('app.timezone'),
            'without_overlapping' => $event->withoutOverlapping,
            'on_one_server' => $event->onOneServer,
            'output_path' => $outputPath,
            'output_exists' => $outputPath !== null && File::exists($outputPath),
            'output_size' => $this->formatBytes($outputPath),
            'output_updated_at' => $this->outputUpdatedAt($outputPath),
            'runs' => $this->runsForEvent($event),
            'run_route' => str_contains((string) $event->command, 'tinkoff:sync-bank')
                ? route('scheduler.run', ['task' => 'tinkoff-bank'])
                : null,
        ];
    }

    /**
     * @return array<int, object>
     */
    private function runsForEvent(Event $event): array
    {
        if (! str_contains((string) $event->command, 'tinkoff:sync-bank')) {
            return [];
        }

        $runs = DB::table('legal.api_sync_runs')
            ->where('provider', 'tinkoff')
            ->where('type', 'bank_sync')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

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

                return $run;
            })
            ->all();
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
            ->timezone(config('app.timezone'))
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
            ->timezone(config('app.timezone'))
            ->format('d.m.Y H:i');
    }

    private function formatNullableDate(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return Carbon::parse((string) $date)
            ->timezone(config('app.timezone'))
            ->format('d.m.Y H:i:s');
    }
}
