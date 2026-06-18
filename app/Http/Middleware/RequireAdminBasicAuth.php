<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('admin.basic_auth.enabled')) {
            return $next($request);
        }

        $expectedUser = (string) config('admin.basic_auth.user');
        $expectedPassword = (string) config('admin.basic_auth.password');
        $user = (string) $request->getUser();
        $password = (string) $request->getPassword();

        if (
            $expectedUser !== ''
            && $expectedPassword !== ''
            && hash_equals($expectedUser, $user)
            && hash_equals($expectedPassword, $password)
        ) {
            return $next($request);
        }

        return response('Authentication required.', 401, [
            'WWW-Authenticate' => 'Basic realm="Legal"',
        ]);
    }
}
