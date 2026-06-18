<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->testUser());
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
        Artisan::shouldReceive('call')
            ->once()
            ->with('tinkoff:sync-bank', ['--days' => config('bank.tinkoff.sync_days')])
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
            ['email' => 'scheduler@example.com'],
            [
                'name' => 'Scheduler User',
                'password' => 'secret',
                'is_active' => true,
            ],
        );
    }
}
