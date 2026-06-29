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
            foreach (range(1, 7) as $index) {
                DB::table('legal.api_sync_requests')->insert([
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
                    'requested_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->get(route('scheduler.index'))
                ->assertOk()
                ->assertSee('scheduler-test-7')
                ->assertDontSee('scheduler-test-1');
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
                '--detail-limit' => 500,
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
