<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->is_active) {
            Auth::logout();

            return redirect()->guest(route('login'));
        }

        $request->attributes->set('authenticated_user', $user);

        $impersonatedUserId = $request->session()->get('impersonated_user_id');
        if (! $user->isAdmin() || ! $impersonatedUserId) {
            $request->session()->forget(['impersonator_user_id', 'impersonated_user_id']);

            return $next($request);
        }

        $impersonatedUser = User::query()
            ->whereKey($impersonatedUserId)
            ->where('is_active', true)
            ->first();

        if (! $impersonatedUser) {
            $request->session()->forget(['impersonator_user_id', 'impersonated_user_id']);

            return $next($request);
        }

        Auth::setUser($impersonatedUser);
        $request->setUserResolver(fn () => $impersonatedUser);
        $request->attributes->set('impersonated_user', $impersonatedUser);
        $request->attributes->set('is_impersonating', true);

        return $next($request);
    }
}
