<?php

namespace App\Services\Hh;

use App\Models\ApiCredential;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class HhResumeSyncService
{
    public function __construct(
        private readonly HhApiClient $client,
        private readonly HhResumeAnalyzer $analyzer,
    ) {
    }

    /**
     * @return array{sync_run_id: int, vacancy_id: string, collections: int, negotiations: int, resumes: int, pdfs: int}
     */
    public function sync(string $vacancyId, User $user, array $runContext = []): array
    {
        $credential = $this->client->activeTokenForUser((int) $user->getKey());

        if (! $credential instanceof ApiCredential) {
            throw new RuntimeException('HH OAuth token is not connected for current user.');
        }

        $runId = $this->startRun($runContext);
        $summary = [
            'sync_run_id' => $runId,
            'vacancy_id' => $vacancyId,
            'collections' => 0,
            'negotiations' => 0,
            'resumes' => 0,
            'pdfs' => 0,
        ];

        try {
            $collectionsPayload = $this->client->get($credential, $runId, '/negotiations', [
                'vacancy_id' => $vacancyId,
                'with_generated_collections' => true,
            ]);

            $this->persistVacancy($vacancyId, $collectionsPayload);

            $collectionUrls = $this->collectionUrls($collectionsPayload);
            $summary['collections'] = count($collectionUrls);

            foreach ($collectionUrls as $collectionUrl) {
                foreach ($this->fetchCollectionItems($credential, $runId, $collectionUrl) as $item) {
                    $resume = Arr::get($item, 'resume');

                    if (! is_array($resume)) {
                        continue;
                    }

                    $resumeId = (string) Arr::get($resume, 'id', '');

                    if ($resumeId === '') {
                        continue;
                    }

                    $analysis = $this->analyzer->analyze($resume);
                    $pdfPath = $this->downloadPdf($credential, $runId, $vacancyId, $resumeId, $resume);

                    if ($pdfPath !== null) {
                        $summary['pdfs']++;
                    }

                    $this->persistNegotiation($vacancyId, $item, $resume, $analysis, $pdfPath);
                    $summary['negotiations']++;
                    $summary['resumes']++;
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
     * @return array<int, array<string, mixed>>
     */
    private function fetchCollectionItems(ApiCredential $credential, int $runId, string $collectionUrl): array
    {
        $items = [];
        $page = 0;

        do {
            $payload = $this->client->get($credential, $runId, $collectionUrl, [
                'page' => $page,
                'per_page' => 50,
            ]);

            foreach ((array) Arr::get($payload, 'items', []) as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }

            $pages = (int) Arr::get($payload, 'pages', 1);
            $page++;
        } while ($page < $pages);

        return $items;
    }

    /**
     * @return list<string>
     */
    private function collectionUrls(array $payload): array
    {
        $urls = [];
        $collections = array_merge(
            (array) Arr::get($payload, 'collections', []),
            (array) Arr::get($payload, 'generated_collections', []),
        );

        $walk = function (array $collection) use (&$walk, &$urls): void {
            $url = Arr::get($collection, 'url');

            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }

            foreach ((array) Arr::get($collection, 'sub_collections', []) as $subCollection) {
                if (is_array($subCollection)) {
                    $walk($subCollection);
                }
            }
        };

        foreach ($collections as $collection) {
            if (is_array($collection)) {
                $walk($collection);
            }
        }

        return array_values(array_unique($urls));
    }

    private function persistVacancy(string $vacancyId, array $raw): void
    {
        $now = now();

        DB::statement(<<<'SQL'
INSERT INTO legal.hh_vacancies (
    hh_vacancy_id,
    name,
    employer_id,
    employer_name,
    alternate_url,
    raw,
    last_synced_at,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?)
ON CONFLICT (hh_vacancy_id) DO UPDATE SET
    name = EXCLUDED.name,
    employer_id = EXCLUDED.employer_id,
    employer_name = EXCLUDED.employer_name,
    alternate_url = EXCLUDED.alternate_url,
    raw = EXCLUDED.raw,
    last_synced_at = EXCLUDED.last_synced_at,
    updated_at = EXCLUDED.updated_at
SQL, [
            $vacancyId,
            Arr::get($raw, 'vacancy.name'),
            Arr::get($raw, 'vacancy.employer.id'),
            Arr::get($raw, 'vacancy.employer.name'),
            Arr::get($raw, 'vacancy.alternate_url'),
            $this->json($raw),
            $now,
            $now,
            $now,
        ]);
    }

    private function persistNegotiation(string $vacancyId, array $item, array $resume, array $analysis, ?string $pdfPath): void
    {
        $resumeId = (string) Arr::get($resume, 'id');
        $now = now();

        DB::statement(<<<'SQL'
INSERT INTO legal.hh_negotiations (
    hh_id,
    hh_vacancy_id,
    resume_id,
    candidate_name,
    resume_title,
    area_name,
    status_id,
    status_name,
    salary_text,
    alternate_url,
    resume_url,
    pdf_url,
    pdf_path,
    responded_at,
    updated_at_hh,
    downloaded_at,
    analyzed_at,
    analysis_score,
    analysis_summary,
    analysis_flags,
    raw,
    resume_raw,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, ?::jsonb, ?, ?)
ON CONFLICT (hh_vacancy_id, resume_id) DO UPDATE SET
    hh_id = EXCLUDED.hh_id,
    candidate_name = EXCLUDED.candidate_name,
    resume_title = EXCLUDED.resume_title,
    area_name = EXCLUDED.area_name,
    status_id = EXCLUDED.status_id,
    status_name = EXCLUDED.status_name,
    salary_text = EXCLUDED.salary_text,
    alternate_url = EXCLUDED.alternate_url,
    resume_url = EXCLUDED.resume_url,
    pdf_url = EXCLUDED.pdf_url,
    pdf_path = COALESCE(EXCLUDED.pdf_path, legal.hh_negotiations.pdf_path),
    responded_at = EXCLUDED.responded_at,
    updated_at_hh = EXCLUDED.updated_at_hh,
    downloaded_at = EXCLUDED.downloaded_at,
    analyzed_at = EXCLUDED.analyzed_at,
    analysis_score = EXCLUDED.analysis_score,
    analysis_summary = EXCLUDED.analysis_summary,
    analysis_flags = EXCLUDED.analysis_flags,
    raw = EXCLUDED.raw,
    resume_raw = EXCLUDED.resume_raw,
    updated_at = EXCLUDED.updated_at
SQL, [
            Arr::get($item, 'id'),
            $vacancyId,
            $resumeId,
            $this->candidateName($resume),
            Arr::get($resume, 'title'),
            Arr::get($resume, 'area.name'),
            Arr::get($item, 'employer_state.id'),
            Arr::get($item, 'employer_state.name'),
            $this->salaryText(Arr::get($resume, 'salary')),
            Arr::get($resume, 'alternate_url'),
            Arr::get($resume, 'url'),
            Arr::get($resume, 'download.pdf.url') ?: Arr::get($resume, 'actions.download.pdf.url'),
            $pdfPath,
            $this->date(Arr::get($item, 'created_at') ?: Arr::get($resume, 'created_at')),
            $this->date(Arr::get($item, 'updated_at')),
            $now,
            $now,
            $analysis['score'],
            $analysis['summary'],
            $this->json($analysis['flags']),
            $this->json($item),
            $this->json($resume),
            $now,
            $now,
        ]);
    }

    private function downloadPdf(ApiCredential $credential, int $runId, string $vacancyId, string $resumeId, array $resume): ?string
    {
        if (! (bool) config('services.hh.download_resumes', true)) {
            return null;
        }

        $url = Arr::get($resume, 'download.pdf.url') ?: Arr::get($resume, 'actions.download.pdf.url');

        if (! is_string($url) || $url === '') {
            return null;
        }

        $content = $this->client->download($credential, $runId, $url);
        $path = "hh/resumes/{$vacancyId}/{$resumeId}.pdf";

        Storage::disk('local')->put($path, $content);

        return $path;
    }

    private function candidateName(array $resume): ?string
    {
        $parts = array_filter([
            Arr::get($resume, 'last_name'),
            Arr::get($resume, 'first_name'),
            Arr::get($resume, 'middle_name'),
        ]);

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return Arr::get($resume, 'title');
    }

    private function salaryText(mixed $salary): ?string
    {
        if (! is_array($salary)) {
            return null;
        }

        $amount = Arr::get($salary, 'amount');
        $currency = Arr::get($salary, 'currency');

        if ($amount === null) {
            return null;
        }

        return trim(number_format((float) $amount, 0, ',', ' ').' '.(string) $currency);
    }

    private function startRun(array $context): int
    {
        $now = now();

        return (int) DB::table('legal.api_sync_runs')->insertGetId([
            'provider' => 'hh',
            'type' => 'resume_responses_sync',
            'status' => 'started',
            'started_by_type' => $context['started_by_type'] ?? 'console',
            'started_by_user_id' => $context['started_by_user_id'] ?? null,
            'started_from' => $context['started_from'] ?? 'cli',
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'api_sync_run_id');
    }

    private function finishRun(int $runId, string $status, array $summary, ?Throwable $exception = null): void
    {
        DB::table('legal.api_sync_runs')
            ->where('api_sync_run_id', $runId)
            ->update([
                'status' => $status,
                'operations_count' => $summary['negotiations'] ?? 0,
                'requests_count' => DB::table('legal.api_sync_requests')->where('api_sync_run_id', $runId)->count(),
                'error' => $exception?->getMessage(),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
