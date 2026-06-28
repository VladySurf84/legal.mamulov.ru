<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class HhResumePageTest extends TestCase
{
    public function test_admin_opens_hh_resumes_page(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'hh-resumes-page@example.com'],
            [
                'name' => 'HH Resumes Admin',
                'password' => 'secret',
                'is_admin' => true,
                'is_active' => true,
            ],
        );

        $this->actingAs($user)
            ->get(route('hh-resumes.index'))
            ->assertOk()
            ->assertSee('HH');
    }
}