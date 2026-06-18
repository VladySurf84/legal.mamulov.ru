<?php

namespace Tests\Feature;

use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
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

    public function test_google_login_link_is_shown_when_configured(): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('auth.google.redirect'));
    }

    public function test_google_login_rejects_email_that_is_not_allowed(): void
    {
        config([
            'admin.auth.google_allowed_emails' => ['owner@example.com'],
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($this->googleUser('guest@example.com'));

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($provider);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('admin_authenticated')
            ->assertSessionHasErrors('login');
    }

    public function test_google_login_allows_configured_email(): void
    {
        config([
            'admin.auth.google_allowed_emails' => ['owner@example.com'],
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($this->googleUser('owner@example.com'));

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($provider);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('bank-accounts.index'))
            ->assertSessionHas('admin_authenticated', true)
            ->assertSessionHas('admin_auth_method', 'google')
            ->assertSessionHas('admin_email', 'owner@example.com');
    }

    private function googleUser(string $email): User
    {
        return new class($email) implements User
        {
            public function __construct(private readonly string $email) {}

            public function getId()
            {
                return '1';
            }

            public function getNickname()
            {
                return null;
            }

            public function getName()
            {
                return 'Owner';
            }

            public function getEmail()
            {
                return $this->email;
            }

            public function getAvatar()
            {
                return null;
            }
        };
    }
}
