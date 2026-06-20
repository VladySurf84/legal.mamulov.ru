<?php

namespace App\Services\Bank;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class TinkoffBusinessClient
{
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
            $this->logRequest($syncRunId, 'GET', $endpoint, $url, $params, $response, $startedAt);
            $response->throw();

            $json = $response->json();

            return is_array($json) ? $json : [];
        } catch (Throwable $exception) {
            if (! isset($response)) {
                $this->logRequest($syncRunId, 'GET', $endpoint, $url, $params, null, $startedAt, $exception);
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
    ): void {
        $body = $response?->body();
        $json = $response?->json();
        $now = now();

        DB::table('legal.api_sync_requests')->insert([
            'api_sync_run_id' => $syncRunId,
            'provider' => 'tinkoff',
            'method' => $method,
            'endpoint' => $endpoint,
            'url' => $url,
            'params' => $this->json($params),
            'http_status' => $response?->status(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'response_hash' => $body !== null ? hash('sha256', $body) : null,
            'response_body' => $body,
            'response_json' => is_array($json) ? $this->json($json) : null,
            'error' => $exception?->getMessage() ?: ($response?->failed() ? $response->body() : null),
            'requested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
