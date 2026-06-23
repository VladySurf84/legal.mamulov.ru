<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __invoke(string $secret, Request $request, TelegramUpdateHandler $handler): JsonResponse
    {
        $expectedSecret = (string) config('services.telegram.webhook_secret');

        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $secret)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            return response()->json(['ok' => true, 'recorded' => false]);
        }

        try {
            $result = $handler->handle($payload);
        } catch (Throwable $exception) {
            Log::warning('Telegram webhook update handling failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'update_id' => $payload['update_id'] ?? null,
            ]);

            return response()->json(['ok' => false], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'ok' => true,
            'recorded' => $result->recorded(),
            'login_link_sent' => $result->loginLinkSent(),
        ]);
    }
}
