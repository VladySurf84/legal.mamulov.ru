<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Telegram\TelegramLoginLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Throwable;

class AdminAuthController extends Controller
{
    public function create(Request $request, TelegramLoginLinkService $telegramLoginLinks): View|RedirectResponse
    {
        $this->rememberTelegramLink($request);

        if (Auth::check()) {
            if ($this->attachPendingTelegramLink($request, Auth::user(), $telegramLoginLinks)) {
                return redirect()
                    ->route('users.index')
                    ->with('status', 'Telegram подключен.');
            }

            return redirect()->route('bank-accounts.index');
        }

        return view('auth.login');
    }

    public function store(Request $request, TelegramLoginLinkService $telegramLoginLinks): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt([
            'email' => strtolower($credentials['login']),
            'password' => $credentials['password'],
            'is_active' => true,
        ])) {
            $request->session()->regenerate();
            $request->user()?->forceFill(['last_login_at' => now()])->save();

            if ($request->user() && $this->attachPendingTelegramLink($request, $request->user(), $telegramLoginLinks)) {
                return redirect()
                    ->route('users.index')
                    ->with('status', 'Telegram подключен.');
            }

            return redirect()->intended(route('bank-accounts.index'));
        }

        return back()
            ->withErrors(['login' => 'Неверный логин или пароль.'])
            ->onlyInput('login');
    }

    public function redirectToGoogle(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()
                ->route('login')
                ->withErrors(['login' => 'Вход через Google пока не настроен.']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request, TelegramLoginLinkService $telegramLoginLinks): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            Log::warning('Google OAuth callback failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'has_code' => $request->has('code'),
                'has_state' => $request->has('state'),
                'redirect_uri' => config('services.google.redirect'),
            ]);

            return redirect()
                ->route('login')
                ->withErrors(['login' => 'Не удалось войти через Google. Попробуйте еще раз.']);
        }

        $email = strtolower((string) $googleUser->getEmail());
        $user = $email === ''
            ? null
            : User::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->where('is_active', true)
                ->first();

        if (! $user) {
            return redirect()
                ->route('login')
                ->withErrors(['login' => 'Этот Google-аккаунт не имеет доступа.']);
        }

        $user->forceFill([
            'google_id' => $googleUser->getId() ?: $user->google_id,
            'name' => $user->name ?: ($googleUser->getName() ?: $email),
            'avatar' => $googleUser->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        $request->session()->regenerate();
        Auth::login($user);

        if ($this->attachPendingTelegramLink($request, $user, $telegramLoginLinks)) {
            return redirect()
                ->route('users.index')
                ->with('status', 'Telegram подключен.');
        }

        return redirect()->intended(route('bank-accounts.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function rememberTelegramLink(Request $request): void
    {
        $token = $request->query('telegram_link');

        if (is_string($token) && trim($token) !== '') {
            $request->session()->put('telegram_login_link_token', trim($token));
        }
    }

    private function attachPendingTelegramLink(Request $request, User $user, TelegramLoginLinkService $telegramLoginLinks): bool
    {
        $token = $request->session()->pull('telegram_login_link_token');

        if (! is_string($token) || trim($token) === '') {
            return false;
        }

        try {
            $telegramLoginLinks->attachUserByToken($user, trim($token));
        } catch (RuntimeException $exception) {
            report($exception);

            return false;
        }

        return true;
    }
}
