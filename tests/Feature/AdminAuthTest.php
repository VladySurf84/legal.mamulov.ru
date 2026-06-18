<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('bank-accounts.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_login_and_logout(): void
    {
        config([
            'admin.auth.user' => 'owner',
            'admin.auth.password' => 'secret',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Вход');

        $this->post(route('login.store'), [
            'login' => 'owner',
            'password' => 'secret',
        ])
            ->assertRedirect(route('bank-accounts.index'))
            ->assertSessionHas('admin_authenticated', true);

        $this->withSession(['admin_authenticated' => true])
            ->post(route('logout'))
            ->assertRedirect(route('login'));
    }
}
