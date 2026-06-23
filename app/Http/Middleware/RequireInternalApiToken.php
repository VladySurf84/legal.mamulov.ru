<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireInternalApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('internal_api.signature_sync_token');

        if ($expectedToken === '') {
            return response()->json([
                'message' => 'Internal signature API token is not configured.',
            ], 503);
        }

        $token = (string) $request->bearerToken();

        if ($token === '') {
            $token = (string) $request->header('X-Internal-Api-Token');
        }

        if ($token !== '' && hash_equals($expectedToken, $token)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Invalid internal API token.',
        ], 401);
    }
}
