<?php

namespace Tests\Feature;

use Tests\TestCase;

class SchedulerPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession(['admin_authenticated' => true]);
    }

    public function test_the_application_opens_scheduler(): void
    {
        $this->get(route('scheduler.index'))
            ->assertOk()
            ->assertSee('Планировщик')
            ->assertSee('tinkoff:sync-bank');
    }
}
