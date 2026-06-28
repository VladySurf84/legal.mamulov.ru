<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireHhBrowserCaptureToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('services.hh.browser_capture_token');

        if ($expectedToken === '') {
            return response()->json([
                'message' => 'HH browser capture token is not configured.',
            ], 503);
        }

        $token = (string) $request->bearerToken();

        if ($token === '') {
            $token = (string) $request->header('X-HH-Capture-Token');
        }

        if ($token !== '' && hash_equals($expectedToken, $token)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Invalid HH browser capture token.',
        ], 401);
    }
}
