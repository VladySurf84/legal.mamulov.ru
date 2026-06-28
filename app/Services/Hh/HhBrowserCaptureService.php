<?php

namespace App\Services\Hh;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;

class HhBrowserCaptureService
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @return array{id: int, action: string, vacancy_id: string|null, resume_id: string|null}
     *
     * @throws JsonException
     */
    public function store(array $payload, array $context = []): array
    {
        $pageUrl = $this->text(Arr::get($payload, 'page.url')) ?? '';
        $originalUrl = $this->originalUrl($payload) ?? $pageUrl;
        $vacancyId = $this->vacancyId($payload);
        $resumeId = $this->resumeId($payload);
        $capturedAt = $this->date(Arr::get($payload, 'capturedAt')) ?? now();
        $dedupeKey = $this->dedupeKey($payload, $vacancyId, $resumeId);

        $row = DB::selectOne(<<<'SQL'
INSERT INTO legal.hh_browser_captures (
    dedupe_key,
    source,
    page_url,
    original_url,
    page_title,
    hh_vacancy_id,
    vacancy_title,
    vacancy_url,
    resume_id,
    candidate_name,
    candidate_resume_url,
    candidate_location,
    candidate_age,
    response_status,
    cover_letter,
    raw_text,
    raw_links,
    resume_structured,
    payload,
    captured_by_user_id,
    captured_ip,
    captured_user_agent,
    captured_at,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, ?::jsonb, ?, ?::inet, ?, ?, ?, ?)
ON CONFLICT (dedupe_key) DO UPDATE SET
    source = EXCLUDED.source,
    page_url = EXCLUDED.page_url,
    original_url = EXCLUDED.original_url,
    page_title = EXCLUDED.page_title,
    hh_vacancy_id = EXCLUDED.hh_vacancy_id,
    vacancy_title = EXCLUDED.vacancy_title,
    vacancy_url = EXCLUDED.vacancy_url,
    resume_id = EXCLUDED.resume_id,
    candidate_name = EXCLUDED.candidate_name,
    candidate_resume_url = EXCLUDED.candidate_resume_url,
    candidate_location = EXCLUDED.candidate_location,
    candidate_age = EXCLUDED.candidate_age,
    response_status = EXCLUDED.response_status,
    cover_letter = EXCLUDED.cover_letter,
    raw_text = EXCLUDED.raw_text,
    raw_links = EXCLUDED.raw_links,
    resume_structured = EXCLUDED.resume_structured,
    payload = EXCLUDED.payload,
    captured_by_user_id = EXCLUDED.captured_by_user_id,
    captured_ip = EXCLUDED.captured_ip,
    captured_user_agent = EXCLUDED.captured_user_agent,
    captured_at = EXCLUDED.captured_at,
    updated_at = EXCLUDED.updated_at
RETURNING hh_browser_capture_id, (xmax = 0) AS inserted
SQL, [
            $dedupeKey,
            $this->text(Arr::get($payload, 'source')) ?? 'hh-browser-extension',
            $pageUrl,
            $originalUrl,
            $this->text(Arr::get($payload, 'page.title')),
            $vacancyId,
            $this->text(Arr::get($payload, 'vacancy.title')),
            $this->text(Arr::get($payload, 'vacancy.url')),
            $resumeId,
            $this->text(Arr::get($payload, 'candidate.name')),
            $this->text(Arr::get($payload, 'candidate.resumeUrl')),
            $this->text(Arr::get($payload, 'candidate.location')),
            $this->text(Arr::get($payload, 'candidate.age')),
            $this->text(Arr::get($payload, 'response.status')),
            $this->text(Arr::get($payload, 'response.coverLetter')),
            $this->rawText(Arr::get($payload, 'raw.text')),
            $this->json($this->array(Arr::get($payload, 'raw.links'))),
            $this->json($this->array(Arr::get($payload, 'resumeStructured'))),
            $this->json($payload),
            $context['captured_by_user_id'] ?? null,
            $this->text($context['ip'] ?? null),
            $this->text($context['user_agent'] ?? null),
            $capturedAt,
            now(),
            now(),
        ]);

        $this->mirrorVacanciesPage($payload, $capturedAt);
        $this->mirrorIntoResumeTables($payload, $vacancyId, $resumeId, $capturedAt);

        return [
            'id' => (int) $row->hh_browser_capture_id,
            'action' => (bool) $row->inserted ? 'inserted' : 'updated',
            'vacancy_id' => $vacancyId,
            'resume_id' => $resumeId,
        ];
    }


    /**
     * @param list<string> $resumeIds
     * @return list<string>
     */
    public function downloadedResumeIds(?string $vacancyId, array $resumeIds): array
    {
        $resumeIds = array_values(array_unique(array_filter(array_map(
            fn (mixed $value): ?string => $this->text(is_scalar($value) ? (string) $value : null),
            $resumeIds,
        ))));

        if ($resumeIds === []) {
            return [];
        }

        $query = DB::table('legal.hh_browser_captures')
            ->whereIn('resume_id', $resumeIds);

        if ($vacancyId !== null && $vacancyId !== '') {
            $query->where('hh_vacancy_id', $vacancyId);
        }

        return $query
            ->pluck('resume_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
    /** @param array<string, mixed> $payload */
    private function mirrorVacanciesPage(array $payload, Carbon $capturedAt): void
    {
        $vacancies = $this->array(Arr::get($payload, 'vacancies'));

        if ($vacancies === []) {
            return;
        }

        $now = now();

        foreach ($vacancies as $vacancy) {
            if (! is_array($vacancy)) {
                continue;
            }

            $vacancyId = $this->text(Arr::get($vacancy, 'id'));

            if ($vacancyId === null) {
                $vacancyId = $this->vacancyId(['vacancy' => ['url' => Arr::get($vacancy, 'url')]]);
            }

            if ($vacancyId === null) {
                continue;
            }

            DB::statement(<<<'SQL'
INSERT INTO legal.hh_vacancies (
    hh_vacancy_id,
    name,
    alternate_url,
    raw,
    last_synced_at,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?::jsonb, ?, ?, ?)
ON CONFLICT (hh_vacancy_id) DO UPDATE SET
    name = COALESCE(EXCLUDED.name, legal.hh_vacancies.name),
    alternate_url = COALESCE(EXCLUDED.alternate_url, legal.hh_vacancies.alternate_url),
    raw = CASE
        WHEN legal.hh_vacancies.raw IS NULL THEN EXCLUDED.raw
        ELSE legal.hh_vacancies.raw || jsonb_build_object('browser_vacancy', EXCLUDED.raw)
    END,
    last_synced_at = EXCLUDED.last_synced_at,
    updated_at = EXCLUDED.updated_at
SQL, [
                $vacancyId,
                $this->text(Arr::get($vacancy, 'title')),
                $this->text(Arr::get($vacancy, 'url')),
                $this->json($vacancy),
                $capturedAt,
                $now,
                $now,
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function mirrorIntoResumeTables(array $payload, ?string $vacancyId, ?string $resumeId, Carbon $capturedAt): void
    {
        if ($vacancyId === null) {
            return;
        }

        $now = now();
        $vacancyTitle = $this->text(Arr::get($payload, 'vacancy.title'));
        $vacancyUrl = $this->text(Arr::get($payload, 'vacancy.url'));

        DB::statement(<<<'SQL'
INSERT INTO legal.hh_vacancies (
    hh_vacancy_id,
    name,
    alternate_url,
    raw,
    last_synced_at,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?::jsonb, ?, ?, ?)
ON CONFLICT (hh_vacancy_id) DO UPDATE SET
    name = COALESCE(legal.hh_vacancies.name, EXCLUDED.name),
    alternate_url = COALESCE(legal.hh_vacancies.alternate_url, EXCLUDED.alternate_url),
    raw = CASE
        WHEN legal.hh_vacancies.raw IS NULL THEN EXCLUDED.raw
        ELSE legal.hh_vacancies.raw || jsonb_build_object('browser_capture', EXCLUDED.raw)
    END,
    updated_at = EXCLUDED.updated_at
SQL, [
            $vacancyId,
            $vacancyTitle,
            $vacancyUrl,
            $this->json($payload),
            $capturedAt,
            $now,
            $now,
        ]);

        if ($resumeId === null) {
            return;
        }

        $status = $this->text(Arr::get($payload, 'response.status'));
        $resumeUrl = $this->text(Arr::get($payload, 'candidate.resumeUrl'));
        $resumeTitle = $this->text(Arr::get($payload, 'candidate.title')) ?? $this->text(Arr::get($payload, 'candidate.name'));

        DB::statement(<<<'SQL'
INSERT INTO legal.hh_negotiations (
    hh_vacancy_id,
    resume_id,
    candidate_name,
    resume_title,
    area_name,
    status_id,
    status_name,
    alternate_url,
    resume_url,
    responded_at,
    raw,
    resume_raw,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, ?, ?)
ON CONFLICT (hh_vacancy_id, resume_id) DO UPDATE SET
    candidate_name = COALESCE(legal.hh_negotiations.candidate_name, EXCLUDED.candidate_name),
    resume_title = COALESCE(legal.hh_negotiations.resume_title, EXCLUDED.resume_title),
    area_name = COALESCE(legal.hh_negotiations.area_name, EXCLUDED.area_name),
    status_id = COALESCE(legal.hh_negotiations.status_id, EXCLUDED.status_id),
    status_name = COALESCE(legal.hh_negotiations.status_name, EXCLUDED.status_name),
    alternate_url = COALESCE(legal.hh_negotiations.alternate_url, EXCLUDED.alternate_url),
    resume_url = COALESCE(legal.hh_negotiations.resume_url, EXCLUDED.resume_url),
    responded_at = COALESCE(legal.hh_negotiations.responded_at, EXCLUDED.responded_at),
    raw = legal.hh_negotiations.raw || jsonb_build_object('browser_capture', EXCLUDED.raw),
    resume_raw = CASE
        WHEN legal.hh_negotiations.resume_raw IS NULL THEN EXCLUDED.resume_raw
        ELSE legal.hh_negotiations.resume_raw || jsonb_build_object('browser_capture', EXCLUDED.resume_raw)
    END,
    updated_at = EXCLUDED.updated_at
SQL, [
            $vacancyId,
            $resumeId,
            $this->text(Arr::get($payload, 'candidate.name')),
            $resumeTitle,
            $this->text(Arr::get($payload, 'candidate.location')),
            $this->statusId($status),
            $status,
            $resumeUrl,
            $resumeUrl,
            $capturedAt,
            $this->json($payload),
            $this->json([
                'id' => $resumeId,
                'title' => $resumeTitle,
                'alternate_url' => $resumeUrl,
                'area' => ['name' => $this->text(Arr::get($payload, 'candidate.location'))],
                'browser_capture' => $payload,
            ]),
            $now,
            $now,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function originalUrl(array $payload): ?string
    {
        foreach ([
            Arr::get($payload, 'page.originalUrl'),
            Arr::get($payload, 'candidate.resumeUrl'),
            Arr::get($payload, 'resumeStructured.originalUrl'),
            Arr::get($payload, 'page.url'),
        ] as $value) {
            $url = $this->text($value);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }
    /** @param array<string, mixed> $payload */
    private function vacancyId(array $payload): ?string
    {
        $explicit = $this->text(Arr::get($payload, 'vacancy.id'));

        if ($explicit !== null) {
            return $explicit;
        }

        foreach ([Arr::get($payload, 'vacancy.url'), Arr::get($payload, 'page.url')] as $url) {
            if (is_string($url) && preg_match('~/vacancy/(\d+)~', $url, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function resumeId(array $payload): ?string
    {
        $urls = [
            Arr::get($payload, 'page.originalUrl'),
            Arr::get($payload, 'candidate.resumeUrl'),
            Arr::get($payload, 'resumeStructured.originalUrl'),
            Arr::get($payload, 'page.url'),
        ];

        foreach ($urls as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            $query = parse_url($url, PHP_URL_QUERY);
            parse_str(is_string($query) ? $query : '', $params);

            foreach (['resumeId', 'resume_id'] as $key) {
                if (isset($params[$key]) && is_scalar($params[$key]) && (string) $params[$key] !== '') {
                    return (string) $params[$key];
                }
            }
        }

        foreach ($urls as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            if (preg_match('~/resume/([A-Za-z0-9]+)~', $url, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function dedupeKey(array $payload, ?string $vacancyId, ?string $resumeId): string
    {
        if ($vacancyId !== null && $resumeId !== null) {
            return hash('sha256', implode('|', ['negotiation', $vacancyId, $resumeId]));
        }

        $parts = array_filter([
            $resumeId,
            $this->text(Arr::get($payload, 'candidate.resumeUrl')),
            $vacancyId,
            $this->text(Arr::get($payload, 'vacancy.url')),
            $this->text(Arr::get($payload, 'page.url')),
        ]);

        return hash('sha256', implode('|', $parts));
    }

    private function statusId(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $status = mb_strtolower($status);
        $status = preg_replace('/[^a-zа-я0-9]+/u', '_', $status) ?: $status;

        return trim($status, '_') ?: null;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $value === '' ? null : $value;
    }


    private function rawText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(str_replace("\0", '', $value));

        return $value === '' ? null : $value;
    }
    /** @return array<int|string, mixed> */
    private function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @param array<int|string, mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
