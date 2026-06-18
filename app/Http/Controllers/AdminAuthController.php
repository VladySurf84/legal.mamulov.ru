<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AdminAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (session('admin_authenticated') === true) {
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

        $expectedUser = (string) config('admin.auth.user');
        $expectedPassword = (string) config('admin.auth.password');

        if (
            $expectedUser !== ''
            && $expectedPassword !== ''
            && hash_equals($expectedUser, $credentials['login'])
            && hash_equals($expectedPassword, $credentials['password'])
        ) {
            $request->session()->regenerate();
            $request->session()->put('admin_authenticated', true);
            $request->session()->put('admin_auth_method', 'password');

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
        } catch (Throwable) {
            return redirect()
                ->route('login')
                ->withErrors(['login' => 'Не удалось войти через Google. Попробуйте еще раз.']);
        }

        $email = strtolower((string) $googleUser->getEmail());
        $allowedEmails = config('admin.auth.google_allowed_emails', []);

        if ($email === '' || ! in_array($email, $allowedEmails, true)) {
            return redirect()
                ->route('login')
                ->withErrors(['login' => 'Этот Google-аккаунт не имеет доступа.']);
        }

        $request->session()->regenerate();
        $request->session()->put('admin_authenticated', true);
        $request->session()->put('admin_auth_method', 'google');
        $request->session()->put('admin_email', $email);
        $request->session()->put('admin_name', $googleUser->getName());

        return redirect()->intended(route('bank-accounts.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'admin_authenticated',
            'admin_auth_method',
            'admin_email',
            'admin_name',
        ]);
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
