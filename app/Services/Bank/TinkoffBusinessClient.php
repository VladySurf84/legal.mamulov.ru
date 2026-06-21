<?php

namespace App\Services\Bank;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class TinkoffBusinessClient
{
    private ?int $lastRequestId = null;

    public function accounts(string $token, int $syncRunId): array
    {
        $response = $this->get($token, $syncRunId, '/api/v3/bank-accounts');

        return $this->listFromResponse($response, ['accounts', 'bankAccounts']);
    }

    public function statement(string $token, int $syncRunId, string $accountNumber, string $from, string $till): array
    {
        return $this->get($token, $syncRunId, '/api/v1/bank-statement', [
            'accountNumber' => $accountNumber,
            'from' => $from,
            'till' => $till,
        ]);
    }

    /**
     * @return array{data: array<string, mixed>|array<int, mixed>, api_sync_request_id: int|null}
     */
    public function statementWithRequest(string $token, int $syncRunId, string $accountNumber, string $from, string $till): array
    {
        $data = $this->statement($token, $syncRunId, $accountNumber, $from, $till);

        return [
            'data' => $data,
            'api_sync_request_id' => $this->lastRequestId,
        ];
    }

    private function request(string $token): PendingRequest
    {
        return Http::acceptJson()
            ->withToken($token)
            ->timeout(60)
            ->retry(2, 500);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('bank.tinkoff.base_url'), '/').$path;
    }

    private function get(string $token, int $syncRunId, string $endpoint, array $params = []): array
    {
        $startedAt = microtime(true);
        $url = $this->url($endpoint);

        try {
            $response = $this->request($token)->get($url, $params);
            $this->lastRequestId = $this->logRequest($syncRunId, 'GET', $endpoint, $url, $params, $response, $startedAt);
            $response->throw();

            $json = $response->json();

            return is_array($json) ? $json : [];
        } catch (Throwable $exception) {
            if (! isset($response)) {
                $this->lastRequestId = $this->logRequest($syncRunId, 'GET', $endpoint, $url, $params, null, $startedAt, $exception);
            }

            throw $exception;
        }
    }

    private function logRequest(
        int $syncRunId,
        string $method,
        string $endpoint,
        string $url,
        array $params,
        ?Response $response,
        float $startedAt,
        ?Throwable $exception = null,
    ): int {
        $body = $response?->body();
        $now = now();

        $row = DB::selectOne(<<<'SQL'
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
    ?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?, ?, CASE WHEN ?::text IS NULL THEN NULL ELSE ?::jsonb END, ?, ?, ?, ?
)
RETURNING api_sync_request_id
SQL, [
            $syncRunId,
            'tinkoff',
            $method,
            $endpoint,
            $url,
            $this->json($params),
            $response?->status(),
            (int) round((microtime(true) - $startedAt) * 1000),
            $body !== null ? hash('sha256', $body) : null,
            $body,
            $body,
            $body,
            $exception?->getMessage() ?: ($response?->failed() ? $response->body() : null),
            $now,
            $now,
            $now,
        ]);

        return (int) $row->api_sync_request_id;
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function listFromResponse(mixed $response, array $keys): array
    {
        if (! is_array($response)) {
            return [];
        }

        if (array_is_list($response)) {
            return $response;
        }

        foreach ($keys as $key) {
            if (is_array($response[$key] ?? null)) {
                return $response[$key];
            }
        }

        return [];
    }
}
