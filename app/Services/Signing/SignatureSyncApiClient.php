<?php

namespace App\Services\Signing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SignatureSyncApiClient
{
    /**
     * @return array<string, mixed>
     */
    public function import(): array
    {
        try {
            $response = $this->request()->post('/api/internal/signatures/import');
        } catch (ConnectionException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), $response->body()));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Signature sync API returned invalid JSON.');
        }

        return $payload;
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('internal_api.signature_sync_base_url'), '/');
        $token = (string) config('internal_api.signature_sync_token');

        if ($baseUrl === '') {
            throw new RuntimeException('SIGNATURE_SYNC_API_BASE_URL is not configured.');
        }

        if ($token === '') {
            throw new RuntimeException('SIGNATURE_SYNC_API_TOKEN is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout((int) config('internal_api.signature_sync_timeout'));
    }

    private function errorMessage(mixed $json, string $body): string
    {
        if (is_array($json) && isset($json['message']) && is_string($json['message'])) {
            return $json['message'];
        }

        return trim($body) !== '' ? trim($body) : 'Signature sync API request failed.';
    }
}
