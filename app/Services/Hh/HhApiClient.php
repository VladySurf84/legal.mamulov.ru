<?php

namespace App\Services\Hh;

use App\Models\ApiCredential;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class HhApiClient
{
    public function authorizationUrl(string $state): string
    {
        return rtrim((string) config('services.hh.auth_url'), '?').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post((string) config('services.hh.token_url'), [
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri' => $this->redirectUri(),
                'code' => $code,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->body() ?: 'HH OAuth token exchange failed.');
        }

        return $this->tokenPayload($response->json());
    }

    public function storeTokenForUser(int $userId, array $payload): ApiCredential
    {
        $now = now();

        return ApiCredential::query()->updateOrCreate(
            [
                'provider' => 'hh',
                'credential_type' => 'oauth_token',
                'owner_type' => 'user',
                'owner_id' => (string) $userId,
                'status' => 'active',
            ],
            [
                'name' => 'HH OAuth token',
                'encrypted_secret' => ApiCredential::encryptSecret($this->json($payload)),
                'meta' => [
                    'expires_at' => $payload['expires_at'] ?? null,
                    'token_type' => $payload['token_type'] ?? null,
                ],
                'last_used_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function activeTokenForUser(int $userId): ?ApiCredential
    {
        return ApiCredential::query()
            ->where('provider', 'hh')
            ->where('credential_type', 'oauth_token')
            ->where('owner_type', 'user')
            ->where('owner_id', (string) $userId)
            ->where('status', 'active')
            ->orderByDesc('api_credential_id')
            ->first();
    }

    /**
     * @return array<string, mixed>|array<int, mixed>
     */
    public function get(ApiCredential $credential, int $syncRunId, string $endpoint, array $params = []): array
    {
        $payload = $this->validTokenPayload($credential);
        $url = str_starts_with($endpoint, 'http') ? $endpoint : $this->url($endpoint);

        return $this->requestJson($credential, $payload, $syncRunId, 'GET', $endpoint, $url, $params);
    }

    public function download(ApiCredential $credential, int $syncRunId, string $url): string
    {
        $payload = $this->validTokenPayload($credential);
        $startedAt = microtime(true);
        $status = null;
        $body = null;
        $contentType = null;

        try {
            $response = $this->pending($payload)->accept('*/*')->get($url);
            $status = $response->status();
            $body = $response->body();
            $contentType = $response->header('Content-Type');
            $this->logRequest($syncRunId, 'GET', $this->endpointFromUrl($url), $url, [], $status, $body, $contentType, $startedAt);

            if (! $response->successful()) {
                throw new RuntimeException($body ?: "HH download failed with status {$status}.");
            }

            return $body;
        } catch (Throwable $exception) {
            $this->logRequest($syncRunId, 'GET', $this->endpointFromUrl($url), $url, [], $status, $body, $contentType, $startedAt, $exception);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validTokenPayload(ApiCredential $credential): array
    {
        $payload = $credential->secretPayload();
        $expiresAt = isset($payload['expires_at']) ? Carbon::parse((string) $payload['expires_at']) : null;

        if ($expiresAt === null || $expiresAt->subMinutes(5)->isFuture()) {
            return $payload;
        }

        $refreshToken = (string) ($payload['refresh_token'] ?? '');

        if ($refreshToken === '') {
            return $payload;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post((string) config('services.hh.token_url'), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
            ]);

        if (! $response->successful()) {
            return $payload;
        }

        $refreshed = $this->tokenPayload($response->json());
        $credential->forceFill([
            'encrypted_secret' => ApiCredential::encryptSecret($this->json($refreshed)),
            'meta' => [
                'expires_at' => $refreshed['expires_at'] ?? null,
                'token_type' => $refreshed['token_type'] ?? null,
            ],
            'last_used_at' => now(),
        ])->save();

        return $refreshed;
    }

    /**
     * @return array<string, mixed>|array<int, mixed>
     */
    private function requestJson(
        ApiCredential $credential,
        array $tokenPayload,
        int $syncRunId,
        string $method,
        string $endpoint,
        string $url,
        array $params = [],
    ): array {
        $startedAt = microtime(true);
        $status = null;
        $body = null;
        $contentType = null;

        try {
            $response = $this->pending($tokenPayload)->send($method, $url, ['query' => $params]);
            $status = $response->status();
            $body = $response->body();
            $contentType = $response->header('Content-Type');
            $this->logRequest($syncRunId, $method, $endpoint, $url, $params, $status, $body, $contentType, $startedAt);

            if (! $response->successful()) {
                throw new RuntimeException($body ?: "HH API request failed with status {$status}.");
            }

            $credential->forceFill(['last_used_at' => now()])->save();
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return is_array($json) ? $json : [];
        } catch (Throwable $exception) {
            $this->logRequest($syncRunId, $method, $endpoint, $url, $params, $status, $body, $contentType, $startedAt, $exception);

            throw $exception;
        }
    }

    private function pending(array $tokenPayload): PendingRequest
    {
        return Http::acceptJson()
            ->withToken((string) Arr::get($tokenPayload, 'access_token'))
            ->withHeaders([
                'HH-User-Agent' => (string) config('services.hh.user_agent'),
                'User-Agent' => (string) config('services.hh.user_agent'),
            ])
            ->timeout((int) config('services.hh.timeout', 60))
            ->retry(1, 500);
    }

    private function logRequest(
        int $syncRunId,
        string $method,
        string $endpoint,
        string $url,
        array $params,
        ?int $status,
        ?string $body,
        ?string $contentType,
        float $startedAt,
        ?Throwable $exception = null,
    ): void {
        static $logged = [];

        $key = implode('|', [$syncRunId, $method, $endpoint, (string) $startedAt]);

        if (isset($logged[$key])) {
            return;
        }

        $logged[$key] = true;
        $now = now();
        $jsonBody = $this->jsonBody($body);

        DB::insert(<<<'SQL'
INSERT INTO legal.api_sync_requests (
    api_sync_run_id,
    provider,
    method,
    endpoint,
    url,
    params,
    http_status,
    duration_ms,
    response_hash,
    response_content_type,
    response_body,
    response_json,
    error,
    requested_at,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?, ?, ?, CASE WHEN ?::text IS NULL THEN NULL ELSE ?::jsonb END, ?, ?, ?, ?)
SQL, [
            $syncRunId,
            'hh',
            $method,
            $endpoint,
            $url,
            $this->json($params),
            $status,
            (int) round((microtime(true) - $startedAt) * 1000),
            $body !== null ? hash('sha256', $body) : null,
            $contentType,
            $body,
            $jsonBody,
            $jsonBody,
            $exception?->getMessage() ?: ($status !== null && ($status < 200 || $status >= 300) ? $body : null),
            $now,
            $now,
            $now,
        ]);
    }

    private function url(string $endpoint): string
    {
        return rtrim((string) config('services.hh.base_url'), '/').'/'.ltrim($endpoint, '/');
    }

    private function endpointFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $url;
    }

    private function tokenPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            throw new RuntimeException('HH OAuth response has unexpected structure.');
        }

        $expiresIn = (int) ($payload['expires_in'] ?? 0);

        if ($expiresIn > 0) {
            $payload['expires_at'] = now()->addSeconds($expiresIn)->toIso8601String();
        }

        return $payload;
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function jsonBody(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        json_decode($body);

        return json_last_error() === JSON_ERROR_NONE ? $body : null;
    }

    private function clientId(): string
    {
        $value = (string) config('services.hh.client_id');

        if ($value === '') {
            throw new RuntimeException('HH_CLIENT_ID is not configured.');
        }

        return $value;
    }

    private function clientSecret(): string
    {
        $value = (string) config('services.hh.client_secret');

        if ($value === '') {
            throw new RuntimeException('HH_CLIENT_SECRET is not configured.');
        }

        return $value;
    }

    private function redirectUri(): string
    {
        return (string) config('services.hh.redirect_uri');
    }
}
