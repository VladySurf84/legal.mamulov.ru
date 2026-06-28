<?php

namespace App\Services\Hh;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class HhResumeBatchAnalysisService
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    /**
     * @return array{batch_id: int, openai_batch_id: string, total_count: int}
     */
    public function submit(?string $vacancyId, ?int $userId = null, string $requestedFrom = 'ui'): array
    {
        $apiKey = $this->apiKey();
        $model = $this->model();
        $resumes = $this->resumesForBatch($vacancyId);

        if ($resumes->isEmpty()) {
            throw new InvalidArgumentException('Нет резюме для оценки.');
        }

        $now = Carbon::now();
        $batchId = (int) DB::table('legal.hh_resume_analysis_batches')->insertGetId([
            'status' => 'draft',
            'hh_vacancy_id' => $vacancyId,
            'model' => $model,
            'total_count' => $resumes->count(),
            'processed_count' => 0,
            'failed_count' => 0,
            'requested_by_user_id' => $userId,
            'requested_from' => $requestedFrom,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'hh_resume_analysis_batch_id');

        $jsonlPath = $this->writeJsonl($batchId, $resumes, $model);

        try {
            $file = $this->uploadInputFile($apiKey, $jsonlPath, $batchId);
            $openaiBatch = $this->createOpenAiBatch($apiKey, (string) data_get($file, 'id'));
        } finally {
            @unlink($jsonlPath);
        }

        DB::table('legal.hh_resume_analysis_batches')
            ->where('hh_resume_analysis_batch_id', $batchId)
            ->update([
                'openai_batch_id' => (string) data_get($openaiBatch, 'id'),
                'input_file_id' => (string) data_get($file, 'id'),
                'status' => (string) data_get($openaiBatch, 'status', 'submitted'),
                'submitted_at' => $now,
                'raw' => json_encode(['file' => $file, 'batch' => $openaiBatch], JSON_UNESCAPED_UNICODE),
                'updated_at' => Carbon::now(),
            ]);

        return [
            'batch_id' => $batchId,
            'openai_batch_id' => (string) data_get($openaiBatch, 'id'),
            'total_count' => $resumes->count(),
        ];
    }

    /**
     * @return array{checked: int, updated: int, failed: int}
     */
    public function pollPending(): array
    {
        $summary = ['checked' => 0, 'updated' => 0, 'failed' => 0];

        DB::table('legal.hh_resume_analysis_batches')
            ->whereNotNull('openai_batch_id')
            ->whereNotIn('status', ['completed', 'failed', 'cancelled', 'expired'])
            ->orderBy('hh_resume_analysis_batch_id')
            ->chunkById(20, function (Collection $batches) use (&$summary): void {
                foreach ($batches as $batch) {
                    $summary['checked']++;
                    $result = $this->pollBatch($batch);
                    $summary['updated'] += $result['updated'];
                    $summary['failed'] += $result['failed'];
                }
            }, 'hh_resume_analysis_batch_id');

        return $summary;
    }

    /**
     * @return array{updated: int, failed: int}
     */
    public function pollBatch(object $batch): array
    {
        $apiKey = $this->apiKey();
        $remote = $this->openaiGet($apiKey, '/batches/'.$batch->openai_batch_id);
        $status = (string) data_get($remote, 'status', $batch->status);
        $outputFileId = data_get($remote, 'output_file_id');
        $errorFileId = data_get($remote, 'error_file_id');
        $now = Carbon::now();

        DB::table('legal.hh_resume_analysis_batches')
            ->where('hh_resume_analysis_batch_id', $batch->hh_resume_analysis_batch_id)
            ->update([
                'status' => $status,
                'output_file_id' => is_scalar($outputFileId) ? (string) $outputFileId : null,
                'error_file_id' => is_scalar($errorFileId) ? (string) $errorFileId : null,
                'last_polled_at' => $now,
                'completed_at' => in_array($status, ['completed', 'failed', 'cancelled', 'expired'], true) ? $now : null,
                'raw' => json_encode($remote, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);

        if ($status !== 'completed' || ! is_string($outputFileId) || $outputFileId === '') {
            return ['updated' => 0, 'failed' => 0];
        }

        $content = $this->downloadFileContent($apiKey, $outputFileId);
        $result = $this->applyOutput($content);

        DB::table('legal.hh_resume_analysis_batches')
            ->where('hh_resume_analysis_batch_id', $batch->hh_resume_analysis_batch_id)
            ->update([
                'processed_count' => $result['updated'],
                'failed_count' => $result['failed'],
                'updated_at' => Carbon::now(),
            ]);

        return $result;
    }

    private function apiKey(): string
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY не задан.');
        }

        return $apiKey;
    }

    private function model(): string
    {
        $model = (string) config('services.openai.model', 'gpt-4.1-mini');

        if ($model === '') {
            throw new RuntimeException('OPENAI_MODEL не задан.');
        }

        return $model;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
    }

    private function timeout(): int
    {
        return max(10, (int) config('services.openai.timeout', 120));
    }

    private function resumesForBatch(?string $vacancyId): Collection
    {
        $captures = DB::table('legal.hh_browser_captures')
            ->select('hh_vacancy_id', 'resume_id', DB::raw('max(hh_browser_capture_id) as hh_browser_capture_id'))
            ->whereNotNull('hh_vacancy_id')
            ->whereNotNull('resume_id')
            ->groupBy('hh_vacancy_id', 'resume_id');

        $query = DB::table('legal.hh_negotiations as n')
            ->leftJoin('legal.hh_vacancies as v', 'v.hh_vacancy_id', '=', 'n.hh_vacancy_id')
            ->leftJoinSub($captures, 'bc', function ($join): void {
                $join->on('bc.hh_vacancy_id', '=', 'n.hh_vacancy_id')
                    ->on('bc.resume_id', '=', 'n.resume_id');
            })
            ->leftJoin('legal.hh_browser_captures as capture', 'capture.hh_browser_capture_id', '=', 'bc.hh_browser_capture_id')
            ->select([
                'n.hh_negotiation_id',
                'n.hh_vacancy_id',
                'n.resume_id',
                'n.candidate_name',
                'n.resume_title',
                'n.area_name',
                'n.salary_text',
                'n.raw',
                'n.resume_raw',
                'v.name as vacancy_name',
                'capture.resume_structured as browser_resume_structured',
                'capture.raw_text as browser_raw_text',
            ])
            ->orderBy('n.hh_negotiation_id');

        if ($vacancyId !== null && $vacancyId !== '') {
            $query->where('n.hh_vacancy_id', $vacancyId);
        }

        return $query->get();
    }

    private function writeJsonl(int $batchId, Collection $resumes, string $model): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hh-openai-batch-');

        if ($path === false) {
            throw new RuntimeException('Не удалось создать временный JSONL файл.');
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Не удалось открыть временный JSONL файл.');
        }

        foreach ($resumes as $resume) {
            fwrite($handle, json_encode($this->batchLine($batchId, $resume, $model), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        }

        fclose($handle);

        return $path;
    }

    private function batchLine(int $batchId, object $resume, string $model): array
    {
        return [
            'custom_id' => 'hh_resume:'.$resume->hh_negotiation_id,
            'method' => 'POST',
            'url' => '/v1/responses',
            'body' => [
                'model' => $model,
                'input' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->userPrompt($resume),
                    ],
                ],
                'max_output_tokens' => 700,
                'metadata' => [
                    'local_batch_id' => (string) $batchId,
                    'hh_negotiation_id' => (string) $resume->hh_negotiation_id,
                ],
            ],
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Ты оцениваешь отклик кандидата на backend/ERP-разработчика для Laravel, PostgreSQL, интеграций, учета и внутренних бизнес-систем.
Верни только JSON без markdown:
{"score":0-100,"summary":"1-3 предложения по-русски","flags":[{"label":"короткий фактор","weight":-20..20,"matched":true|false}]}
Оценивай не по ключевым словам, а по пригодности к ERP: архитектурное мышление, backend/API, БД, интеграции, учетные домены, самостоятельность, риски.
PROMPT;
    }

    private function userPrompt(object $resume): string
    {
        $payload = [
            'vacancy' => [
                'id' => $resume->hh_vacancy_id,
                'name' => $resume->vacancy_name,
            ],
            'candidate' => [
                'name' => $resume->candidate_name,
                'area' => $resume->area_name,
                'salary' => $resume->salary_text,
            ],
            'resume' => [
                'id' => $resume->resume_id,
                'title' => $resume->resume_title,
                'api' => $this->compactJson($resume->resume_raw),
                'browser' => $this->compactJson($resume->browser_resume_structured),
                'text' => $this->limitText((string) ($resume->browser_raw_text ?? ''), 12000),
            ],
            'negotiation' => $this->compactJson($resume->raw),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function compactJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return $this->limitText($value, 8000);
        }

        return $decoded;
    }

    private function limitText(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return Str::limit($text, $limit, '...');
    }

    private function uploadInputFile(string $apiKey, string $path, int $batchId): array
    {
        $response = $this->http
            ->withToken($apiKey)
            ->timeout($this->timeout())
            ->attach('file', file_get_contents($path), "hh-resume-analysis-{$batchId}.jsonl")
            ->post($this->baseUrl().'/files', [
                'purpose' => 'batch',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI file upload failed: '.$response->body());
        }

        return $response->json();
    }

    private function createOpenAiBatch(string $apiKey, string $inputFileId): array
    {
        $response = $this->http
            ->withToken($apiKey)
            ->timeout($this->timeout())
            ->post($this->baseUrl().'/batches', [
                'input_file_id' => $inputFileId,
                'endpoint' => '/v1/responses',
                'completion_window' => '24h',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI batch creation failed: '.$response->body());
        }

        return $response->json();
    }

    private function openaiGet(string $apiKey, string $path): array
    {
        $response = $this->http
            ->withToken($apiKey)
            ->timeout($this->timeout())
            ->get($this->baseUrl().$path);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI request failed: '.$response->body());
        }

        return $response->json();
    }

    private function downloadFileContent(string $apiKey, string $fileId): string
    {
        $response = $this->http
            ->withToken($apiKey)
            ->timeout($this->timeout())
            ->get($this->baseUrl().'/files/'.$fileId.'/content');

        if ($response->failed()) {
            throw new RuntimeException('OpenAI output download failed: '.$response->body());
        }

        return $response->body();
    }

    /**
     * @return array{updated: int, failed: int}
     */
    private function applyOutput(string $content): array
    {
        $updated = 0;
        $failed = 0;

        foreach (preg_split('/\R/', trim($content)) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = json_decode($line, true);
            $negotiationId = $this->negotiationIdFromCustomId((string) data_get($row, 'custom_id'));

            if ($negotiationId === null || data_get($row, 'error') !== null || (int) data_get($row, 'response.status_code', 0) >= 400) {
                $failed++;
                continue;
            }

            $analysis = $this->analysisFromResponse((array) data_get($row, 'response.body', []));

            if ($analysis === null) {
                $failed++;
                continue;
            }

            DB::table('legal.hh_negotiations')
                ->where('hh_negotiation_id', $negotiationId)
                ->update([
                    'analyzed_at' => Carbon::now(),
                    'analysis_score' => $analysis['score'],
                    'analysis_summary' => $analysis['summary'],
                    'analysis_flags' => json_encode($analysis['flags'], JSON_UNESCAPED_UNICODE),
                    'updated_at' => Carbon::now(),
                ]);

            $updated++;
        }

        return ['updated' => $updated, 'failed' => $failed];
    }

    private function negotiationIdFromCustomId(string $customId): ?int
    {
        if (preg_match('/^hh_resume:(\d+)$/', $customId, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @return array{score: int, summary: string, flags: array<int, array<string, mixed>>}|null
     */
    private function analysisFromResponse(array $body): ?array
    {
        $text = $this->responseText($body);
        $json = $this->decodeJsonText($text);

        if (! is_array($json)) {
            return null;
        }

        $score = (int) data_get($json, 'score', -1);
        $summary = trim((string) data_get($json, 'summary', ''));
        $flags = data_get($json, 'flags', []);

        if ($score < 0 || $score > 100 || $summary === '') {
            return null;
        }

        return [
            'score' => $score,
            'summary' => $summary,
            'flags' => is_array($flags) ? array_values($flags) : [],
        ];
    }

    private function responseText(array $body): string
    {
        $outputText = data_get($body, 'output_text');

        if (is_string($outputText) && $outputText !== '') {
            return $outputText;
        }

        $parts = [];

        foreach ((array) data_get($body, 'output', []) as $output) {
            foreach ((array) data_get($output, 'content', []) as $content) {
                $text = data_get($content, 'text');

                if (is_string($text)) {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private function decodeJsonText(string $text): mixed
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;

        return json_decode($text, true);
    }
}
