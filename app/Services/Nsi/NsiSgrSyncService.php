<?php

namespace App\Services\Nsi;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class NsiSgrSyncService
{
    private const BASE_URL = 'https://nsi.eaeunion.org/portal/api';
    private const DICTIONARY_CODE = '1995';
    private const PROVIDER = 'nsi_eaeu';
    private const ACTIVE_STATUS_ID = '0888035a-52fa-4e7e-bf59-348c6cc218d4';

    /**
     * @param array<string, mixed> $options
     * @return array<string, int|string|null>
     */
    public function syncList(array $options = []): array
    {
        $actualDate = $this->actualDate($options);
        $limit = $this->positiveInt($options['limit'] ?? 1000, 1000);
        $pauseMs = $this->nonNegativeInt($options['pause_ms'] ?? 300);
        $maxPages = $this->nonNegativeInt($options['max_pages'] ?? 0);
        $timeout = $this->positiveInt($options['timeout'] ?? 60, 60);
        $maxRetries = $this->nonNegativeInt($options['max_retries'] ?? 5);
        $errorPauseMs = $this->nonNegativeInt($options['error_pause_ms'] ?? 10000);
        $reset = (bool) ($options['reset'] ?? false);
        $startOffset = $options['start_offset'] ?? null;
        $runId = $this->startRun('sgr_list_sync', $options);

        $summary = [
            'sync_run_id' => $runId,
            'total_count' => 0,
            'pages' => 0,
            'records' => 0,
            'inserted' => 0,
            'updated' => 0,
            'next_offset' => 0,
        ];

        try {
            $state = $this->listState($actualDate, $limit, $reset);
            $offset = $startOffset === null || $startOffset === ''
                ? (int) $state->next_offset
                : max(0, (int) $startOffset);

            $total = $this->fetchListTotal($runId, $actualDate, $timeout, $maxRetries, $errorPauseMs);
            $summary['total_count'] = $total;
            $this->updateListState([
                'total_count' => $total,
                'next_offset' => $offset,
                'page_limit' => $limit,
                'last_started_at' => now(),
                'last_error' => null,
                'last_error_offset' => null,
                'last_error_at' => null,
            ]);

            while ($offset < $total) {
                if ($maxPages > 0 && $summary['pages'] >= $maxPages) {
                    break;
                }

                try {
                    $items = $this->fetchListPage($runId, $actualDate, $offset, $limit, $timeout, $maxRetries, $errorPauseMs);
                } catch (Throwable $exception) {
                    $this->updateListState([
                        'last_error_offset' => $offset,
                        'last_error' => $exception->getMessage(),
                        'last_error_at' => now(),
                    ]);

                    throw $exception;
                }

                if ($items === []) {
                    break;
                }

                foreach ($items as $item) {
                    $inserted = $this->upsertListItem($item);
                    $summary['records']++;
                    $summary[$inserted ? 'inserted' : 'updated']++;
                }

                $offset += count($items);
                $summary['pages']++;
                $summary['next_offset'] = $offset;

                $this->updateListState([
                    'next_offset' => $offset,
                    'last_success_offset' => $offset,
                    'last_success_at' => now(),
                    'last_finished_at' => now(),
                    'meta' => $this->json([
                        'last_page_records' => count($items),
                        'last_run_id' => $runId,
                    ]),
                ]);

                $this->pause($pauseMs);

                if (count($items) < $limit) {
                    break;
                }
            }

            $this->finishRun($runId, 'success', $summary);

            return $summary;
        } catch (Throwable $exception) {
            $this->finishRun($runId, 'failed', $summary, $exception);

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, int|string|null>
     */
    public function syncDetails(array $options = []): array
    {
        $actualDate = $this->actualDate($options);
        $pauseMs = $this->nonNegativeInt($options['pause_ms'] ?? 300);
        $timeout = $this->positiveInt($options['timeout'] ?? 60, 60);
        $maxRetries = $this->nonNegativeInt($options['max_retries'] ?? 5);
        $errorPauseMs = $this->nonNegativeInt($options['error_pause_ms'] ?? 10000);
        $detailLimit = $this->nonNegativeInt($options['detail_limit'] ?? 1000);
        $refreshActiveAfterHours = $this->nonNegativeInt($options['refresh_active_after_hours'] ?? 24);
        $number = $this->text($options['number'] ?? null);
        $runId = $this->startRun('sgr_detail_sync', $options);

        $summary = [
            'sync_run_id' => $runId,
            'records' => 0,
            'details' => 0,
            'failed' => 0,
        ];

        try {
            $records = $this->pendingDetailRecords($number, $detailLimit);

            if ($records->isEmpty() && $number === null && $refreshActiveAfterHours > 0) {
                $records = $this->activeRefreshDetailRecords($refreshActiveAfterHours, $detailLimit);
            }

            foreach ($records as $record) {
                $summary['records']++;

                try {
                    $payload = $this->fetchDetail($runId, (string) $record->nsi_id, $actualDate, $timeout, $maxRetries, $errorPauseMs);
                    $this->applyDetailPayload((int) $record->nsi_sgr_record_id, $payload);
                    $summary['details']++;
                } catch (Throwable $exception) {
                    $summary['failed']++;
                    DB::table('legal.nsi_sgr_records')
                        ->where('nsi_sgr_record_id', $record->nsi_sgr_record_id)
                        ->update([
                            'detail_attempts' => DB::raw('detail_attempts + 1'),
                            'detail_sync_error' => $exception->getMessage(),
                            'updated_at' => now(),
                        ]);
                }

                $this->pause($pauseMs);
            }

            $this->finishRun($runId, 'success', $summary);

            return $summary;
        } catch (Throwable $exception) {
            $this->finishRun($runId, 'failed', $summary, $exception);

            throw $exception;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function pendingDetailRecords(?string $number, int $detailLimit): \Illuminate\Support\Collection
    {
        $query = DB::table('legal.nsi_sgr_records')
            ->where(function (Builder $query): void {
                $query->whereNull('detail_payload')
                    ->orWhereColumn('detail_synced_at', '<', 'list_synced_at');
            })
            ->when($number !== null, fn (Builder $query) => $query->where('sgr_number', $number))
            ->orderBy('nsi_sgr_record_id');

        return $this->limitedDetailRecords($query, $detailLimit);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function activeRefreshDetailRecords(int $refreshActiveAfterHours, int $detailLimit): \Illuminate\Support\Collection
    {
        $refreshBefore = now()->subHours($refreshActiveAfterHours);
        $query = DB::table('legal.nsi_sgr_records')
            ->where('status_id', self::ACTIVE_STATUS_ID)
            ->whereNotNull('detail_payload')
            ->where(function (Builder $query) use ($refreshBefore): void {
                $query->whereNull('detail_synced_at')
                    ->orWhere('detail_synced_at', '<=', $refreshBefore);
            })
            ->orderBy('detail_synced_at')
            ->orderBy('nsi_sgr_record_id');

        return $this->limitedDetailRecords($query, $detailLimit);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function limitedDetailRecords(Builder $query, int $detailLimit): \Illuminate\Support\Collection
    {
        if ($detailLimit > 0) {
            $query->limit($detailLimit);
        }

        return $query->get(['nsi_sgr_record_id', 'nsi_id', 'sgr_number']);
    }

    private function fetchListTotal(int $runId, string $actualDate, int $timeout, int $maxRetries, int $errorPauseMs): int
    {
        $payload = [
            'date' => $actualDate,
            'filter' => [],
        ];

        $data = $this->requestWithRetries(
            $runId,
            'POST',
            'dictionaries/'.self::DICTIONARY_CODE.'/get-list-data-total',
            $payload,
            $timeout,
            $maxRetries,
            $errorPauseMs,
        );

        return (int) ($data['byFilterCount'] ?? $data['totalCount'] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchListPage(int $runId, string $actualDate, int $offset, int $limit, int $timeout, int $maxRetries, int $errorPauseMs): array
    {
        $payload = [
            'date' => $actualDate,
            'filter' => [],
            'offset' => $offset,
            'limit' => $limit,
        ];

        $data = $this->requestWithRetries(
            $runId,
            'POST',
            'dictionaries/'.self::DICTIONARY_CODE.'/get-list-data',
            $payload,
            $timeout,
            $maxRetries,
            $errorPauseMs,
        );

        $items = $data['value'] ?? $data;

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchDetail(int $runId, string $nsiId, string $actualDate, int $timeout, int $maxRetries, int $errorPauseMs): array
    {
        return $this->requestWithRetries(
            $runId,
            'GET',
            'dictionaries/'.self::DICTIONARY_CODE.'/get-view-card-data-on-date',
            [
                'id' => $nsiId,
                'date' => $actualDate,
            ],
            $timeout,
            $maxRetries,
            $errorPauseMs,
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function upsertListItem(array $item): bool
    {
        $data = Arr::get($item, 'data', []);
        $number = $this->text(Arr::get($data, 'NUMB_DOC'));

        if ($number === null) {
            throw new RuntimeException('NSI SGR row without NUMB_DOC.');
        }

        $status = Arr::get($data, 'STATUS', []);
        $now = now();
        $row = DB::selectOne(<<<'SQL'
INSERT INTO legal.nsi_sgr_records (
    nsi_id,
    version_id,
    sgr_number,
    status_id,
    status_type,
    status_name,
    serial_number,
    document_date,
    product_name,
    manufacturer_name,
    recipient_name,
    norm_doc,
    use_area,
    protocol,
    date_from,
    date_to,
    date_time_from,
    date_time_to,
    update_date_time,
    source_list_payload,
    list_synced_at,
    created_at,
    updated_at
) VALUES (
    ?::uuid, ?::uuid, ?, ?::uuid, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?
)
ON CONFLICT (sgr_number) DO UPDATE SET
    nsi_id = EXCLUDED.nsi_id,
    version_id = EXCLUDED.version_id,
    status_id = EXCLUDED.status_id,
    status_type = EXCLUDED.status_type,
    status_name = EXCLUDED.status_name,
    serial_number = EXCLUDED.serial_number,
    document_date = EXCLUDED.document_date,
    product_name = EXCLUDED.product_name,
    manufacturer_name = EXCLUDED.manufacturer_name,
    recipient_name = EXCLUDED.recipient_name,
    norm_doc = EXCLUDED.norm_doc,
    use_area = EXCLUDED.use_area,
    protocol = EXCLUDED.protocol,
    date_from = EXCLUDED.date_from,
    date_to = EXCLUDED.date_to,
    date_time_from = EXCLUDED.date_time_from,
    date_time_to = EXCLUDED.date_time_to,
    update_date_time = EXCLUDED.update_date_time,
    source_list_payload = EXCLUDED.source_list_payload,
    list_synced_at = EXCLUDED.list_synced_at,
    updated_at = EXCLUDED.updated_at
RETURNING (xmax = 0) AS inserted
SQL, [
            $this->text($item['id'] ?? null),
            $this->uuid($item['versionId'] ?? null),
            $number,
            $this->uuid(Arr::get($status, 'id')),
            $this->text(Arr::get($status, 'type')),
            $this->text(Arr::get($status, 'name')),
            $this->text(Arr::get($data, 'SERIALNUMB')),
            $this->date(Arr::get($data, 'DATE_DOC')),
            $this->text(Arr::get($data, 'NAME_PROD')),
            $this->text(Arr::get($data, 'FIRMMADE_NAME')),
            $this->text(Arr::get($data, 'FIRMGET_NAME')),
            $this->text(Arr::get($data, 'DOC_NORM')),
            $this->text(Arr::get($data, 'DOC_USEAREA')),
            $this->text(Arr::get($data, 'DOC_PROTOCOL')),
            $this->date($item['dateFrom'] ?? null),
            $this->date($item['dateTo'] ?? null),
            $this->timestamp($item['dateTimeFrom'] ?? null),
            $this->timestamp($item['dateTimeTo'] ?? null),
            $this->timestamp($item['updateDateTime'] ?? null),
            $this->json($item),
            $now,
            $now,
            $now,
        ]);

        return (bool) ($row->inserted ?? false);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyDetailPayload(int $recordId, array $payload): void
    {
        $data = Arr::get($payload, 'data', []);
        $status = Arr::get($data, 'STATUS', []);
        $country = Arr::get($data, 'N_ALFA_NAME', []);
        $blankVersion = Arr::get($data, 'BLANKVER', []);
        $now = now();

        DB::statement(<<<'SQL'
UPDATE legal.nsi_sgr_records
SET
    version_id = ?::uuid,
    status_id = ?::uuid,
    status_type = ?,
    status_name = ?,
    serial_number = ?,
    document_date = ?,
    product_code = ?,
    product_name = ?,
    product_application = ?,
    manufacturer_name = ?,
    manufacturer_address = ?,
    manufacturer_country_id = ?::uuid,
    manufacturer_object = ?::jsonb,
    recipient_name = ?,
    recipient_inn = ?,
    recipient_address = ?,
    recipient_country_id = ?::uuid,
    country_code = ?,
    country_name = ?,
    use_area = ?,
    protocol = ?,
    norm_doc = ?,
    hygienic_characteristics = ?::jsonb,
    signer_name = ?,
    blank_version = ?,
    date_from = ?,
    date_to = ?,
    date_time_from = ?,
    date_time_to = ?,
    update_date_time = ?,
    detail_payload = ?::jsonb,
    detail_synced_at = ?,
    detail_attempts = detail_attempts + 1,
    detail_sync_error = NULL,
    updated_at = ?
WHERE nsi_sgr_record_id = ?
SQL, [
            $this->uuid($payload['versionId'] ?? null),
            $this->uuid(Arr::get($status, 'id')),
            $this->text(Arr::get($status, 'type')),
            $this->text(Arr::get($status, 'name')),
            $this->text(Arr::get($data, 'SERIALNUMB')),
            $this->date(Arr::get($data, 'DATE_DOC')),
            $this->text(Arr::get($data, 'OKP_PROD')),
            $this->text(Arr::get($data, 'NAME_PROD')),
            $this->text(Arr::get($data, 'PROD_APP')),
            $this->text(Arr::get($data, 'FIRMMADE_NAME')),
            $this->text(Arr::get($data, 'FIRMMADE_ADDR')),
            $this->uuid(Arr::get($data, 'FIRMMADE_COUNTRY')),
            $this->json($this->array(Arr::get($data, 'FIRMMADE_OBJ'))),
            $this->text(Arr::get($data, 'FIRMGET_NAME')),
            $this->text(Arr::get($data, 'FIRMGET_INN')),
            $this->text(Arr::get($data, 'FIRMGET_ADDR')),
            $this->uuid(Arr::get($data, 'FIRMGET_COUNTRY')),
            $this->text(Arr::get($data, 'N_ALFA_CODE')),
            $this->text(Arr::get($country, 'name')),
            $this->text(Arr::get($data, 'DOC_USEAREA')),
            $this->text(Arr::get($data, 'DOC_PROTOCOL')),
            $this->text(Arr::get($data, 'DOC_NORM')),
            $this->json($this->array(Arr::get($data, 'DOC_GIGHARK'))),
            $this->text(Arr::get($data, 'WHO')),
            $this->text(Arr::get($blankVersion, 'name')),
            $this->date($payload['dateFrom'] ?? null),
            $this->date($payload['dateTo'] ?? null),
            $this->timestamp($payload['dateTimeFrom'] ?? null),
            $this->timestamp($payload['dateTimeTo'] ?? null),
            $this->timestamp($payload['updateDateTime'] ?? null),
            $this->json($payload),
            $now,
            $now,
            $recordId,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function requestWithRetries(int $runId, string $method, string $endpoint, array $payload, int $timeout, int $maxRetries, int $errorPauseMs): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $maxRetries) {
            $attempt++;

            try {
                return $this->request($runId, $method, $endpoint, $payload, $timeout);
            } catch (Throwable $exception) {
                $lastException = $exception;

                if ($attempt > $maxRetries) {
                    break;
                }

                $this->pause($errorPauseMs);
            }
        }

        throw $lastException ?? new RuntimeException('NSI request failed.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(int $runId, string $method, string $endpoint, array $payload, int $timeout): array
    {
        $url = self::BASE_URL.'/'.$endpoint;
        $started = microtime(true);
        $response = null;
        $error = null;
        $body = null;

        try {
            $pending = Http::acceptJson()
                ->asJson()
                ->timeout($timeout);

            $response = $method === 'GET'
                ? $pending->get($url, $payload)
                : $pending->post($url, $payload);
            $body = $response->body();

            if (! $response->successful()) {
                throw new RuntimeException(sprintf('NSI %s failed with HTTP %d.', $endpoint, $response->status()));
            }

            $data = $response->json();

            if (! is_array($data)) {
                throw new RuntimeException('NSI response is not JSON object.');
            }

            return $data;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();

            throw $exception;
        } finally {
            $this->logRequest($runId, $method, $endpoint, $url, $payload, $response, (int) round((microtime(true) - $started) * 1000), $body, $error);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function logRequest(int $runId, string $method, string $endpoint, string $url, array $params, ?Response $response, int $durationMs, ?string $body, ?string $error): void
    {
        $now = now();

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
    response_json,
    response_body,
    error,
    requested_at,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?, ?::jsonb, ?, ?, ?, ?, ?)
SQL, [
            $runId,
            self::PROVIDER,
            $method,
            substr($endpoint, 0, 255),
            $url,
            $this->json($params),
            $response?->status(),
            $durationMs,
            $body !== null ? hash('sha256', $body) : null,
            $this->jsonBody($body),
            $body !== null ? mb_substr($body, 0, 100000) : null,
            $error,
            $now,
            $now,
            $now,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function startRun(string $type, array $context): int
    {
        $now = now();

        return (int) DB::table('legal.api_sync_runs')->insertGetId([
            'provider' => self::PROVIDER,
            'type' => $type,
            'status' => 'started',
            'started_by_type' => $context['started_by_type'] ?? (app()->runningInConsole() ? 'console' : 'user'),
            'started_by_user_id' => $context['started_by_user_id'] ?? null,
            'started_from' => $context['started_from'] ?? (app()->runningInConsole() ? 'cli' : 'web'),
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'api_sync_run_id');
    }

    /**
     * @param array<string, int|string|null> $summary
     */
    private function finishRun(int $runId, string $status, array $summary, ?Throwable $exception = null): void
    {
        DB::table('legal.api_sync_runs')
            ->where('api_sync_run_id', $runId)
            ->update([
                'status' => $status,
                'operations_count' => (int) ($summary['records'] ?? $summary['details'] ?? 0),
                'requests_count' => DB::table('legal.api_sync_requests')
                    ->where('api_sync_run_id', $runId)
                    ->count(),
                'error' => $exception?->getMessage(),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function listState(string $actualDate, int $limit, bool $reset): object
    {
        $state = DB::table('legal.nsi_sgr_import_state')
            ->where('state_key', 'list')
            ->first();

        if (
            $state === null
            || $reset
            || (string) $state->actual_date !== $actualDate
        ) {
            $now = now();

            DB::statement(<<<'SQL'
INSERT INTO legal.nsi_sgr_import_state (
    state_key,
    dictionary_code,
    actual_date,
    total_count,
    next_offset,
    page_limit,
    last_success_offset,
    last_error_offset,
    last_error,
    last_started_at,
    last_finished_at,
    last_success_at,
    last_error_at,
    meta,
    created_at,
    updated_at
) VALUES ('list', ?, ?, NULL, 0, ?, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ?::jsonb, ?, ?)
ON CONFLICT (state_key) DO UPDATE SET
    dictionary_code = EXCLUDED.dictionary_code,
    actual_date = EXCLUDED.actual_date,
    total_count = EXCLUDED.total_count,
    next_offset = EXCLUDED.next_offset,
    page_limit = EXCLUDED.page_limit,
    last_success_offset = EXCLUDED.last_success_offset,
    last_error_offset = EXCLUDED.last_error_offset,
    last_error = EXCLUDED.last_error,
    last_started_at = EXCLUDED.last_started_at,
    last_finished_at = EXCLUDED.last_finished_at,
    last_success_at = EXCLUDED.last_success_at,
    last_error_at = EXCLUDED.last_error_at,
    meta = EXCLUDED.meta,
    updated_at = EXCLUDED.updated_at
SQL, [
                self::DICTIONARY_CODE,
                $actualDate,
                $limit,
                $this->json([]),
                $now,
                $now,
            ]);
        }

        return DB::table('legal.nsi_sgr_import_state')
            ->where('state_key', 'list')
            ->first();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function updateListState(array $values): void
    {
        $meta = $values['meta'] ?? null;
        unset($values['meta']);
        $values['updated_at'] = now();

        if ($values !== []) {
            DB::table('legal.nsi_sgr_import_state')
                ->where('state_key', 'list')
                ->update($values);
        }

        if ($meta !== null) {
            DB::statement('UPDATE legal.nsi_sgr_import_state SET meta = ?::jsonb, updated_at = ? WHERE state_key = ?', [
                $meta,
                now(),
                'list',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function actualDate(array $options): string
    {
        $date = $options['date'] ?? now()->toDateString();

        return Carbon::parse((string) $date)->toDateString();
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function uuid(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text !== null && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $text)
            ? $text
            : null;
    }

    private function date(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        return Carbon::parse((string) $value)->toDateString();
    }

    private function timestamp(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        return Carbon::parse((string) $value)->timezone('UTC')->format('Y-m-d H:i:s');
    }

    /**
     * @return array<int|string, mixed>
     */
    private function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param array<int|string, mixed> $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function jsonBody(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE ? $body : null;
    }

    private function pause(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    private function positiveInt(mixed $value, int $default): int
    {
        $value = (int) $value;

        return $value > 0 ? $value : $default;
    }

    private function nonNegativeInt(mixed $value): int
    {
        return max(0, (int) $value);
    }
}
