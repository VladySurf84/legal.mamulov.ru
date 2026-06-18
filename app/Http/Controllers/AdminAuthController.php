<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

            return redirect()->intended(route('bank-accounts.index'));
        }

        return back()
            ->withErrors(['login' => 'Неверный логин или пароль.'])
            ->onlyInput('login');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_authenticated');
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
