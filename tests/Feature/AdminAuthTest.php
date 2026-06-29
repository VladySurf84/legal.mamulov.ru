<?php

namespace Tests\Feature;

use App\Models\User as AppUser;
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
        AppUser::query()->where('email', 'owner@example.com')->delete();
        $user = AppUser::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Вход');

        $this->post(route('login.store'), [
            'login' => 'owner@example.com',
            'password' => 'secret',
        ])
            ->assertRedirect(route('bank-accounts.index'));

        $this->assertAuthenticatedAs($user);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
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

    public function test_login_page_has_passkey_entrypoint(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('passkeys.login.options'))
            ->assertSee('Войти по отпечатку или PIN');
    }

    public function test_authenticated_user_can_open_passkey_page_and_request_registration_options(): void
    {
        AppUser::query()->where('email', 'passkey-owner@example.com')->delete();
        $user = AppUser::query()->create([
            'name' => 'Passkey Owner',
            'email' => 'passkey-owner@example.com',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('passkeys.index'))
            ->assertOk()
            ->assertSee('Ключи входа');

        $this->actingAs($user)
            ->postJson(route('passkeys.register.options'))
            ->assertOk()
            ->assertJsonPath('publicKey.user.name', 'passkey-owner@example.com')
            ->assertJsonPath('publicKey.authenticatorSelection.residentKey', 'required')
            ->assertJsonPath('publicKey.authenticatorSelection.userVerification', 'required');
    }

    public function test_passkey_login_options_can_start_without_email(): void
    {
        $this->postJson(route('passkeys.login.options'))
            ->assertOk()
            ->assertJsonPath('publicKey.userVerification', 'required')
            ->assertJsonMissingPath('publicKey.allowCredentials');
    }

    public function test_google_login_rejects_email_that_is_not_allowed(): void
    {
        AppUser::query()->where('email', 'guest@example.com')->delete();

        config([
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
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_google_login_allows_configured_email(): void
    {
        AppUser::query()->where('email', 'owner@example.com')->delete();
        $user = AppUser::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => null,
            'is_active' => true,
        ]);

        config([
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
            ->assertRedirect(route('bank-accounts.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('legal.laravel_users', [
            'email' => 'owner@example.com',
            'google_id' => '1',
        ]);
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
