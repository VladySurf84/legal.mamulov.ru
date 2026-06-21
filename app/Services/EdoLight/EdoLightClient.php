<?php

namespace App\Services\EdoLight;

use App\Models\ApiCredential;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class EdoLightClient
{
    public function __construct(private readonly CryptoProSigner $signer)
    {
    }

    /**
     * @return array{token: string, expireDate?: string, uuidToken?: string}
     */
    public function authenticate(int $legalId, int $syncRunId): array
    {
        $credential = $this->signingCredential($legalId);
        $secret = $credential->secretPayload();

        $thumbprint = (string) ($secret['thumbprint'] ?? $secret['secret'] ?? '');
        $password = isset($secret['password']) ? (string) $secret['password'] : null;

        if ($thumbprint === '') {
            throw new RuntimeException("EDO Light credential #{$credential->api_credential_id} does not contain thumbprint.");
        }

        $authKey = $this->getAuthKey($syncRunId);
        $signature = $this->signer->sign((string) $authKey['data'], $thumbprint, $password);
        $legalInn = $this->legalInn($legalId);

        $payload = [
            'uuid' => $authKey['uuid'],
            'data' => $signature,
            'unitedToken' => true,
        ];

        if ($legalInn !== null) {
            $payload['inn'] = $legalInn;
        }

        $tokenPayload = $this->simpleSignIn($syncRunId, $payload);
        $token = (string) ($tokenPayload['token'] ?? '');

        if ($token === '') {
            throw new RuntimeException('EDO Light auth response does not contain token.');
        }

        $this->storeSessionToken($legalId, $token, $tokenPayload);
        $credential->forceFill(['last_used_at' => now()])->save();

        return $tokenPayload;
    }

    private function signingCredential(int $legalId): ApiCredential
    {
        $credential = ApiCredential::query()
            ->where('provider', 'edo_light')
            ->where('credential_type', 'cryptopro_thumbprint')
            ->where('owner_type', 'legal')
            ->where('owner_id', $legalId)
            ->where('status', 'active')
            ->first();

        if ($credential === null) {
            throw new RuntimeException(
                "No active EDO Light CryptoPro credential found. Expected legal.api_credentials provider=edo_light, credential_type=cryptopro_thumbprint, owner_type=legal, owner_id={$legalId}."
            );
        }

        return $credential;
    }

    /**
     * @return array{uuid: string, data: string}
     */
    private function getAuthKey(int $syncRunId): array
    {
        $endpoint = '/auth/key';
        $response = $this->request($syncRunId, 'GET', $endpoint, $this->trueApiUrl($endpoint));

        return $this->jsonResponse($response, ['uuid', 'data']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function simpleSignIn(int $syncRunId, array $payload): array
    {
        $endpoint = '/auth/simpleSignIn';
        $response = $this->request($syncRunId, 'POST', $endpoint, $this->trueApiUrl($endpoint), $payload);

        return $this->jsonResponse($response);
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function request(int $syncRunId, string $method, string $endpoint, string $url, ?array $json = null): Response
    {
        $startedAt = microtime(true);
        $status = null;
        $body = null;

        try {
            $pending = Http::acceptJson()->timeout(60)->retry(1, 500);
            $response = $method === 'POST'
                ? $pending->asJson()->post($url, $json ?? [])
                : $pending->get($url);

            $status = $response->status();
            $body = $response->body();
            $this->logRequest($syncRunId, $method, $endpoint, $url, $json ?? [], $status, $body, $startedAt);

            if (! $response->successful()) {
                throw new RuntimeException($body !== '' ? $body : "EDO Light auth request failed with status {$status}.");
            }

            return $response;
        } catch (Throwable $exception) {
            if ($body === null) {
                $this->logRequest($syncRunId, $method, $endpoint, $url, $json ?? [], $status, null, $startedAt, $exception);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, string>  $requiredKeys
     * @return array<string, mixed>
     */
    private function jsonResponse(Response $response, array $requiredKeys = []): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('EDO Light auth response is not JSON.');
        }

        foreach ($requiredKeys as $key) {
            if (! isset($payload[$key]) || $payload[$key] === '') {
                throw new RuntimeException("EDO Light auth response does not contain {$key}.");
            }
        }

        return $payload;
    }

    private function trueApiUrl(string $endpoint): string
    {
        return rtrim((string) config('edo_light.true_api_base_url'), '/').$endpoint;
    }

    private function legalInn(int $legalId): ?string
    {
        $inn = DB::table('legal.legal_own')
            ->where('legal_id', $legalId)
            ->value('legal_inn');

        return $inn !== null ? (string) $inn : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeSessionToken(int $legalId, string $token, array $payload): void
    {
        $now = now();

        ApiCredential::query()->updateOrCreate(
            [
                'provider' => 'edo_light',
                'credential_type' => 'session_token',
                'owner_type' => 'legal',
                'owner_id' => $legalId,
                'status' => 'active',
            ],
            [
                'name' => 'EDO Light session token',
                'encrypted_secret' => ApiCredential::encryptSecret($token),
                'meta' => [
                    'expireDate' => $payload['expireDate'] ?? null,
                    'uuidToken' => $payload['uuidToken'] ?? null,
                    'refreshed_at' => $now->toISOString(),
                ],
                'last_used_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function logRequest(
        int $syncRunId,
        string $method,
        string $endpoint,
        string $url,
        array $params,
        ?int $status,
        ?string $body,
        float $startedAt,
        ?Throwable $exception = null,
    ): void {
        $now = now();
        $jsonBody = $this->jsonOrNull($body);

        DB::statement(<<<'SQL'
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
    response_body,
    response_json,
    error,
    requested_at,
    created_at,
    updated_at
) VALUES (
    ?, ?, ?, ?, ?, CASE WHEN ?::text IS NULL THEN NULL ELSE ?::jsonb END, ?, ?, ?, ?, CASE WHEN ?::text IS NULL THEN NULL ELSE ?::jsonb END, ?, ?, ?, ?
)
SQL, [
            $syncRunId,
            'edo_light',
            $method,
            $endpoint,
            $url,
            $params !== [] ? json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null,
            $params !== [] ? json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null,
            $status,
            (int) round((microtime(true) - $startedAt) * 1000),
            $body !== null ? hash('sha256', $body) : null,
            $body,
            $jsonBody,
            $jsonBody,
            $exception?->getMessage() ?: ($status !== null && ($status < 200 || $status >= 300) ? $body : null),
            $now,
            $now,
            $now,
        ]);
    }

    private function jsonOrNull(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        try {
            json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return $body;
        } catch (Throwable) {
            return null;
        }
    }
}
