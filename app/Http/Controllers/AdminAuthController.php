<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AdminAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('bank-accounts.index');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
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

    public function handleGoogleCallback(Request $request): RedirectResponse
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

        return redirect()->intended(route('bank-accounts.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
