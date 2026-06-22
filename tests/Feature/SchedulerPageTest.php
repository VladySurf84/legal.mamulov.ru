<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
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

    private function test_user(): User
    {
        return User::query()->updateOrCreate(
            ['email' => self::TEST_USER_EMAIL],
            [
                'name' => 'Scheduler User',
                'password' => 'secret',
                'is_active' => true,
            ],
        );
    }
}
