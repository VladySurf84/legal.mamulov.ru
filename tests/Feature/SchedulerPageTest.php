<?php

namespace Tests\Feature;

use App\Console\ScheduleDefinitions;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchedulerPageTest extends TestCase
{
    private const TEST_USER_EMAIL = 'scheduler@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->test_user());
    }

    protected function tearDown(): void
    {
        User::query()->where('email', self::TEST_USER_EMAIL)->delete();

        parent::tearDown();
    }

    public function test_the_application_opens_scheduler(): void
    {
        $this->get(route('scheduler.index'))
            ->assertOk()
            ->assertSee('Планировщик')
            ->assertSee('задача планировщика')
            ->assertSee('tinkoff:sync-bank')
            ->assertSee('nsi:sgr-sync')
            ->assertSee('Запустить задание');
    }

    public function test_the_application_runs_scheduler_task(): void
    {
        $userId = $this->test_user()->getKey();

        Artisan::shouldReceive('call')
            ->once()
            ->with('tinkoff:sync-bank', [
                '--days' => config('bank.tinkoff.sync_days'),
                '--started-by-type' => 'user',
                '--started-by-user-id' => $userId,
                '--started-from' => 'ui',
            ])
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('done');

        $this->post(route('scheduler.run', ['task' => 'tinkoff-bank']))
            ->assertRedirect(route('scheduler.index'))
            ->assertSessionHas('status', 'done');
    }

    public function test_scheduled_tasks_use_available_signal_handlers_only(): void
    {
        $schedule = app(Schedule::class);

        ScheduleDefinitions::define($schedule);

        $expected = extension_loaded('pcntl')
            && function_exists('pcntl_async_signals')
            && function_exists('pcntl_signal');

        $this->assertNotEmpty($schedule->events());

        foreach ($schedule->events() as $event) {
            $this->assertSame($expected, $event->releaseOnTerminationSignals);
        }
    }

    public function test_nsi_sgr_detail_task_uses_stable_mutex_name(): void
    {
        $schedule = app(Schedule::class);

        ScheduleDefinitions::define($schedule);

        $detailEvent = collect($schedule->events())->first(
            fn ($event) => str_contains((string) $event->command, 'nsi:sgr-sync --mode=details')
        );

        $this->assertNotNull($detailEvent);
        $this->assertSame('nsi-sgr-sync-details', $detailEvent->mutexName());
    }

    public function test_the_application_opens_scheduler_with_many_logged_requests(): void
    {
        $runId = DB::table('legal.api_sync_runs')->insertGetId([
            'provider' => 'nsi_eaeu',
            'type' => 'sgr_detail_sync',
            'status' => 'success',
            'requests_count' => 7,
            'started_by_type' => 'system',
            'started_from' => 'scheduler',
            'started_at' => now(),
            'finished_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'api_sync_run_id');

        try {
            $latestRequestId = null;

            foreach (range(1, 7) as $index) {
                $requestId = DB::table('legal.api_sync_requests')->insertGetId([
                    'api_sync_run_id' => $runId,
                    'provider' => 'nsi_eaeu',
                    'method' => 'POST',
                    'endpoint' => '/scheduler-test-'.$index,
                    'url' => 'https://example.test/scheduler-test-'.$index,
                    'params' => json_encode([
                        'filter' => ['status' => ['signed', 'active']],
                        'offset' => $index,
                    ], JSON_THROW_ON_ERROR),
                    'http_status' => 200,
                    'duration_ms' => 10,
                    'response_hash' => str_repeat((string) $index, 64),
                    'response_content_type' => 'application/vnd.scheduler-test+json; charset=utf-8',
                    'response_body' => json_encode(['ok' => true, 'index' => $index], JSON_THROW_ON_ERROR),
                    'requested_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'api_sync_request_id');

                if ($index === 7) {
                    $latestRequestId = $requestId;
                }
            }

            $this->get(route('scheduler.index'))
                ->assertOk()
                ->assertSee('scheduler-test-7')
                ->assertSee(route('scheduler.requests.response', ['requestId' => $latestRequestId]))
                ->assertSee('response')
                ->assertDontSee('scheduler-test-1');

            $this->get(route('scheduler.requests.response', ['requestId' => $latestRequestId]))
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.scheduler-test+json; charset=utf-8')
                ->assertSee('"index":7', false);
        } finally {
            DB::table('legal.api_sync_runs')->where('api_sync_run_id', $runId)->delete();
        }
    }

    public function test_started_scheduler_run_shows_live_http_request_count(): void
    {
        $runId = DB::table('legal.api_sync_runs')->insertGetId([
            'provider' => 'nsi_eaeu',
            'type' => 'sgr_detail_sync',
            'status' => 'started',
            'requests_count' => 0,
            'started_by_type' => 'system',
            'started_from' => 'scheduler',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'api_sync_run_id');

        try {
            foreach (range(1, 7) as $index) {
                DB::table('legal.api_sync_requests')->insert([
                    'api_sync_run_id' => $runId,
                    'provider' => 'nsi_eaeu',
                    'method' => 'GET',
                    'endpoint' => '/scheduler-live-'.$index,
                    'url' => 'https://example.test/scheduler-live-'.$index,
                    'http_status' => 200,
                    'duration_ms' => 10,
                    'requested_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->get(route('scheduler.index'))
                ->assertOk()
                ->assertSee('scheduler-live-7')
                ->assertDontSee('scheduler-live-1')
                ->assertSee('Показаны последние 5 из 7 HTTP-запросов.');
        } finally {
            DB::table('legal.api_sync_runs')->where('api_sync_run_id', $runId)->delete();
        }
    }

    public function test_scheduler_response_forces_json_content_type_for_plain_text_json_body(): void
    {
        $runId = DB::table('legal.api_sync_runs')->insertGetId([
            'provider' => 'nsi_eaeu',
            'type' => 'sgr_detail_sync',
            'status' => 'success',
            'requests_count' => 1,
            'started_at' => now(),
            'finished_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'api_sync_run_id');

        try {
            $requestId = DB::table('legal.api_sync_requests')->insertGetId([
                'api_sync_run_id' => $runId,
                'provider' => 'nsi_eaeu',
                'method' => 'GET',
                'endpoint' => '/plain-json',
                'url' => 'https://example.test/plain-json',
                'http_status' => 200,
                'duration_ms' => 10,
                'response_content_type' => 'text/plain; charset=utf-8',
                'response_body' => '{"ok":true}',
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ], 'api_sync_request_id');

            $this->get(route('scheduler.requests.response', ['requestId' => $requestId]))
                ->assertOk()
                ->assertHeader('content-type', 'application/json; charset=UTF-8')
                ->assertSee('{"ok":true}', false);
        } finally {
            DB::table('legal.api_sync_runs')->where('api_sync_run_id', $runId)->delete();
        }
    }

    public function test_scheduler_response_uses_full_json_payload_when_body_is_truncated(): void
    {
        $runId = DB::table('legal.api_sync_runs')->insertGetId([
            'provider' => 'nsi_eaeu',
            'type' => 'sgr_detail_sync',
            'status' => 'success',
            'requests_count' => 1,
            'started_at' => now(),
            'finished_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'api_sync_run_id');

        try {
            $requestId = DB::table('legal.api_sync_requests')->insertGetId([
                'api_sync_run_id' => $runId,
                'provider' => 'nsi_eaeu',
                'method' => 'GET',
                'endpoint' => '/truncated-json',
                'url' => 'https://example.test/truncated-json',
                'http_status' => 200,
                'duration_ms' => 10,
                'response_body' => '[{"id":1,"name":"cut',
                'response_json' => json_encode([
                    ['id' => 1, 'name' => 'full'],
                    ['id' => 2, 'name' => 'tail'],
                ], JSON_THROW_ON_ERROR),
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ], 'api_sync_request_id');

            $this->get(route('scheduler.requests.response', ['requestId' => $requestId]))
                ->assertOk()
                ->assertHeader('content-type', 'application/json; charset=UTF-8')
                ->assertSee('"name": "tail"', false)
                ->assertDontSee('cut', false);
        } finally {
            DB::table('legal.api_sync_runs')->where('api_sync_run_id', $runId)->delete();
        }
    }

    public function test_the_application_runs_nsi_sgr_list_scheduler_task(): void
    {
        $userId = $this->test_user()->getKey();

        Artisan::shouldReceive('call')
            ->once()
            ->with('nsi:sgr-sync', [
                '--mode' => 'list',
                '--limit' => 1000,
                '--max-pages' => 20,
                '--pause-ms' => 300,
                '--error-pause-ms' => 10000,
                '--max-retries' => 5,
                '--started-by-type' => 'user',
                '--started-by-user-id' => $userId,
                '--started-from' => 'ui',
            ])
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('list done');

        $this->post(route('scheduler.run', ['task' => 'nsi-sgr-list']))
            ->assertRedirect(route('scheduler.index'))
            ->assertSessionHas('status', 'list done');
    }

    public function test_the_application_runs_nsi_sgr_detail_scheduler_task(): void
    {
        $userId = $this->test_user()->getKey();

        Artisan::shouldReceive('call')
            ->once()
            ->with('nsi:sgr-sync', [
                '--mode' => 'details',
                '--detail-limit' => 2000,
                '--refresh-active-after-hours' => 24,
                '--pause-ms' => 300,
                '--error-pause-ms' => 10000,
                '--max-retries' => 5,
                '--started-by-type' => 'user',
                '--started-by-user-id' => $userId,
                '--started-from' => 'ui',
            ])
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('details done');

        $this->post(route('scheduler.run', ['task' => 'nsi-sgr-details']))
            ->assertRedirect(route('scheduler.index'))
            ->assertSessionHas('status', 'details done');
    }

    private function test_user(): User
    {
        return User::query()->updateOrCreate(
            ['email' => self::TEST_USER_EMAIL],
            [
                'name' => 'Scheduler User',
                'password' => 'secret',
                'is_admin' => true,
                'is_active' => true,
            ],
        );
    }
}
