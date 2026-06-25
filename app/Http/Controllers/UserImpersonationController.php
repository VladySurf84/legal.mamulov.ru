<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserImpersonationController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $authenticatedUser = $request->attributes->get('authenticated_user') ?: $request->user();

        abort_unless($authenticatedUser instanceof User && $authenticatedUser->isAdmin(), 403);
        abort_if(! $user->is_active, 422, 'Cannot impersonate inactive user.');

        if ($authenticatedUser->is($user)) {
            $request->session()->forget(['impersonator_user_id', 'impersonated_user_id']);

            return back()->with('status', 'Режим работы под другим пользователем отключен.');
        }

        $request->session()->put('impersonator_user_id', $authenticatedUser->getKey());
        $request->session()->put('impersonated_user_id', $user->getKey());

        return back()->with('status', 'Теперь вы работаете как ' . ($user->name ?: $user->email) . '.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['impersonator_user_id', 'impersonated_user_id']);

        return back()->with('status', 'Вы вернулись к своему пользователю.');
    }
}
