<?php

namespace App\Services\EdoLight;

use App\Models\ApiCredential;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class EdoLightClient
{
    private ?int $lastRequestId = null;

    public function __construct(private readonly CryptoProSigner $signer)
    {
    }

    /**
     * @return array{token: string, expireDate?: string, uuidToken?: string}
     */
    public function authenticate(string $legalId, int $syncRunId): array
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
        ];

        if ($legalInn !== null) {
            $payload['inn'] = $legalInn;
        }

        $tokenPayload = $this->simpleSignIn($syncRunId, $payload);
        $token = (string) ($tokenPayload['token'] ?? $tokenPayload['uuidToken'] ?? '');

        if ($token === '') {
            throw new RuntimeException('EDO Light auth response does not contain token or uuidToken.');
        }

        $this->storeSessionToken($legalId, $token, $tokenPayload);
        $credential->forceFill(['last_used_at' => now()])->save();

        return $tokenPayload;
    }

    /**
     * @return array{documents: int, content_downloaded: int}
     */
    public function syncDocuments(
        string $legalId,
        int $syncRunId,
        int $limit = 100,
        int $offset = 0,
        ?int $contentLimit = 5,
        string $direction = 'all',
    ): array {
        $token = (string) ($this->authenticate($legalId, $syncRunId)['token'] ?? '');

        $directions = match ($direction) {
            'incoming' => ['incoming'],
            'outgoing' => ['outgoing'],
            'all' => ['incoming', 'outgoing'],
            default => throw new RuntimeException("Unsupported EDO Light direction {$direction}."),
        };

        $summary = [
            'documents' => 0,
            'content_downloaded' => 0,
        ];

        foreach ($directions as $currentDirection) {
            $payload = $this->documentList($syncRunId, $token, $currentDirection, $limit, $offset);

            foreach ($this->documentsFromListPayload($payload) as $entry) {
                $sourceRecordId = $this->storeDocumentSourceRecord(
                    $legalId,
                    $currentDirection,
                    $entry['document'],
                    $entry['item'],
                    $this->lastRequestId,
                );

                $summary['documents']++;

                if ($contentLimit === null || $summary['content_downloaded'] < $contentLimit) {
                    $documentId = $this->documentExternalId($entry['document']);

                    if ($documentId !== null) {
                        $content = $this->downloadDocumentContent($syncRunId, $token, $currentDirection, $documentId);
                        $this->storeDocumentContentFile($sourceRecordId, $legalId, $currentDirection, $documentId, $content);
                        $summary['content_downloaded']++;
                    }
                }
            }
        }

        return $summary;
    }

    private function signingCredential(string $legalId): ApiCredential
    {
        $credential = ApiCredential::query()
            ->where('provider', 'edo_light')
            ->where('credential_type', 'cryptopro_thumbprint')
            ->where('owner_type', 'legal')
            ->where('owner_id', $legalId)
            ->where('status', 'active')
            ->first();

        if ($credential === null) {
            $credential = ApiCredential::query()
                ->where('provider', 'cryptopro')
                ->where('credential_type', 'certificate_thumbprint')
                ->where('status', 'active')
                ->where(function ($query) use ($legalId): void {
                    $query
                        ->where(function ($query) use ($legalId): void {
                            $query
                                ->where('owner_type', 'legal')
                                ->where('owner_id', $legalId);
                        })
                        ->orWhere('meta->legal_inn', $legalId);
                })
                ->orderByRaw("(meta->>'subject_type' = 'individual_entrepreneur') DESC")
                ->orderBy('api_credential_id')
                ->first();
        }

        if ($credential === null) {
            throw new RuntimeException(
                "No active EDO Light CryptoPro credential found. Expected legal.api_credentials provider=edo_light/cryptopro, owner_type=legal or meta.legal_inn={$legalId}."
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
     * @return array<string, mixed>
     */
    private function documentList(int $syncRunId, string $token, string $direction, int $limit, int $offset): array
    {
        $endpoint = "/api/v1/{$direction}-documents";
        $url = $this->edoLightUrl($endpoint).'?'.http_build_query([
            'limit' => $limit,
            'offset' => $offset,
        ], '', '&', PHP_QUERY_RFC3986);

        $response = $this->request($syncRunId, 'GET', $endpoint, $url, null, $token);

        return $this->jsonResponse($response);
    }

    private function downloadDocumentContent(int $syncRunId, string $token, string $direction, string $documentId): string
    {
        $endpoint = "/api/v1/{$direction}-documents/{$documentId}/content";
        $response = $this->request(
            $syncRunId,
            'GET',
            $endpoint,
            $this->edoLightUrl($endpoint),
            null,
            $token,
            'application/xml',
        );

        return $response->body();
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function request(
        int $syncRunId,
        string $method,
        string $endpoint,
        string $url,
        ?array $json = null,
        ?string $token = null,
        string $accept = 'application/json',
    ): Response
    {
        $startedAt = microtime(true);
        $status = null;
        $body = null;
        $contentType = null;

        try {
            $pending = Http::accept($accept)->timeout(60)->retry(1, 500);

            if ($token !== null && $token !== '') {
                $pending = $pending->withHeader(
                    'Authorization',
                    str_contains($token, '.') ? "Bearer {$token}" : $token,
                );
            }

            $response = $method === 'POST'
                ? $pending->asJson()->post($url, $json ?? [])
                : $pending->get($url);

            $status = $response->status();
            $body = $response->body();
            $contentType = $response->header('Content-Type');
            $this->lastRequestId = $this->logRequest($syncRunId, $method, $endpoint, $url, $json ?? [], $status, $body, $contentType, $startedAt);

            if (! $response->successful()) {
                throw new RuntimeException($body !== '' ? $body : "EDO Light auth request failed with status {$status}.");
            }

            return $response;
        } catch (Throwable $exception) {
            if ($body === null) {
                $this->lastRequestId = $this->logRequest($syncRunId, $method, $endpoint, $url, $json ?? [], $status, null, $contentType, $startedAt, $exception);
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

    private function edoLightUrl(string $endpoint): string
    {
        return rtrim((string) config('edo_light.base_url'), '/').$endpoint;
    }

    private function legalInn(string $legalId): ?string
    {
        $inn = DB::table('legal.legal_own')
            ->where('legal_id', $legalId)
            ->value('legal_inn');

        return $inn !== null ? (string) $inn : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeSessionToken(string $legalId, string $token, array $payload): void
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
     * @param  array<string, mixed>  $payload
     * @return array<int, array{item: array<string, mixed>, document: array<string, mixed>}>
     */
    private function documentsFromListPayload(array $payload): array
    {
        $entries = [];

        foreach ((array) ($payload['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach ((array) ($item['documents'] ?? []) as $document) {
                if (is_array($document)) {
                    $entries[] = [
                        'item' => $item,
                        'document' => $document,
                    ];
                }
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $item
     */
    private function storeDocumentSourceRecord(
        string $legalId,
        string $direction,
        array $document,
        array $item,
        ?int $apiSyncRequestId,
    ): int {
        $externalDocumentId = $this->documentExternalId($document);

        if ($externalDocumentId === null) {
            throw new RuntimeException('EDO Light document list item does not contain document id.');
        }

        $externalId = "edo_light:{$direction}:{$externalDocumentId}";
        $rawPayload = [
            'direction' => $direction,
            'item' => $item,
            'document' => $document,
        ];
        $now = now();
        $recordedAt = $this->timestamp($document['date'] ?? null) ?? $this->timestamp($document['created_at'] ?? null);

        $sourceRecord = DB::selectOne(<<<'SQL'
INSERT INTO legal.source_records (
    source_system,
    source_channel,
    source_record_type,
    external_id,
    external_hash,
    source_api_sync_request_id,
    received_at,
    recorded_at,
    raw_payload,
    metadata,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, ?, ?)
ON CONFLICT (source_system, source_record_type, external_id)
    WHERE external_id IS NOT NULL
DO UPDATE SET
    source_channel = EXCLUDED.source_channel,
    external_hash = EXCLUDED.external_hash,
    source_api_sync_request_id = EXCLUDED.source_api_sync_request_id,
    received_at = EXCLUDED.received_at,
    recorded_at = EXCLUDED.recorded_at,
    raw_payload = EXCLUDED.raw_payload,
    metadata = EXCLUDED.metadata,
    updated_at = EXCLUDED.updated_at
RETURNING source_record_id
SQL, [
            'edo_light',
            'api',
            'edo_document',
            $externalId,
            hash('sha256', $externalId.'|'.$this->json($rawPayload)),
            $apiSyncRequestId,
            $now,
            $recordedAt,
            $this->json($rawPayload),
            $this->json([
                'legal_id' => $legalId,
                'direction' => $direction,
            ]),
            $now,
            $now,
        ]);

        $sourceRecordId = (int) $sourceRecord->source_record_id;

        DB::table('legal.source_record_document_details')->upsert([[
            'source_record_id' => $sourceRecordId,
            'source_document_type' => 'edo_light_document',
            'source_direction' => $direction,
            'source_status' => $this->nullableString($document['status'] ?? null),
            'document_number' => $this->nullableString($document['number'] ?? null),
            'document_date' => $this->dateFromTimestamp($document['date'] ?? null),
            'external_document_id' => $externalDocumentId,
            'external_document_type' => $this->nullableString($document['type'] ?? null),
            'external_status' => $this->nullableString($document['status'] ?? null),
            'processed_at' => $this->timestamp($document['processed_at'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['source_record_id'], [
            'source_document_type',
            'source_direction',
            'source_status',
            'document_number',
            'document_date',
            'external_document_id',
            'external_document_type',
            'external_status',
            'processed_at',
            'updated_at',
        ]);

        return $sourceRecordId;
    }

    private function storeDocumentContentFile(int $sourceRecordId, string $legalId, string $direction, string $documentId, string $content): void
    {
        $extension = str_starts_with(ltrim($content), '<') ? 'xml' : 'bin';
        $fileName = "{$documentId}.{$extension}";
        $storedPath = "edo-light/{$legalId}/{$direction}/{$fileName}";
        $now = now();

        Storage::disk('local')->put($storedPath, $content);

        $values = [
            'source_record_id' => $sourceRecordId,
            'file_role' => 'document_content',
            'source_file_name' => $fileName,
            'stored_path' => $storedPath,
            'mime_type' => $extension === 'xml' ? 'application/xml' : 'application/octet-stream',
            'file_sha256' => hash('sha256', $content),
            'file_size' => strlen($content),
            'encoding' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $existing = DB::table('legal.source_record_files')
            ->where('source_record_id', $sourceRecordId)
            ->where('file_role', 'document_content')
            ->where('source_file_name', $fileName)
            ->first();

        if ($existing === null) {
            DB::table('legal.source_record_files')->insert($values);

            return;
        }

        unset($values['source_record_id'], $values['file_role'], $values['source_file_name'], $values['created_at']);

        DB::table('legal.source_record_files')
            ->where('source_record_file_id', $existing->source_record_file_id)
            ->update($values);
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
        ?string $contentType,
        float $startedAt,
        ?Throwable $exception = null,
    ): int {
        $now = now();
        $bodyForDatabase = $this->bodyForDatabase($body);
        $jsonBody = $this->jsonOrNull($bodyForDatabase);

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
    response_content_type,
    response_body,
    response_json,
    error,
    requested_at,
    created_at,
    updated_at
) VALUES (
    ?, ?, ?, ?, ?, CASE WHEN ?::text IS NULL THEN NULL ELSE ?::jsonb END, ?, ?, ?, ?, ?, CASE WHEN ?::text IS NULL THEN NULL ELSE ?::jsonb END, ?, ?, ?, ?
)
RETURNING api_sync_request_id
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
            $contentType,
            $bodyForDatabase,
            $jsonBody,
            $jsonBody,
            $exception?->getMessage() ?: ($status !== null && ($status < 200 || $status >= 300) ? $bodyForDatabase : null),
            $now,
            $now,
            $now,
        ]);

        return (int) $row->api_sync_request_id;
    }

    private function bodyForDatabase(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return $body;
        }

        if (mb_check_encoding($body, 'UTF-8')) {
            return $body;
        }

        $converted = @mb_convert_encoding($body, 'UTF-8', 'Windows-1251');

        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        return '[base64]'.base64_encode($body);
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

    /**
     * @param  array<string, mixed>  $document
     */
    private function documentExternalId(array $document): ?string
    {
        $id = $document['id'] ?? $document['doc_id'] ?? null;

        if ($id === null || $id === '') {
            return null;
        }

        return (string) $id;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        return Carbon::parse((string) $value);
    }

    private function dateFromTimestamp(mixed $value): ?string
    {
        return $this->timestamp($value)?->toDateString();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
