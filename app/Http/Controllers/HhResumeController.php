<?php

namespace App\Http\Controllers;

use App\Services\Hh\HhApiClient;
use App\Services\Hh\HhResumeBatchAnalysisService;
use App\Services\Hh\HhResumeSyncService;
use App\Support\UserUiSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class HhResumeController extends Controller
{
    public function index(Request $request, HhApiClient $client): View|JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $vacancyId = trim((string) $request->query('vacancy_id', ''));
        $perPage = $this->perPage($request);
        $credential = $client->activeTokenForUser((int) $request->user()->getKey());

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
            ->orderByDesc(DB::raw('COALESCE(n.codex_analysis_score, n.analysis_score)'))
            ->orderByDesc('n.responded_at');

        if ($vacancyId !== '') {
            $query->where('n.hh_vacancy_id', $vacancyId);
        }

        $summary = [
            'count' => (clone $query)->count('n.hh_negotiation_id'),
            'captured_count' => (clone $query)->whereNotNull('bc.hh_browser_capture_id')->count('n.hh_negotiation_id'),
            'high_score_count' => (clone $query)->whereRaw('COALESCE(n.codex_analysis_score, n.analysis_score) >= ?', [75])->count('n.hh_negotiation_id'),
            'pdf_count' => (clone $query)
                ->whereNotNull('n.pdf_path')
                ->where('n.pdf_path', '<>', '')
                ->count('n.hh_negotiation_id'),
        ];

        $columns = [
            'n.*',
            'v.name as vacancy_name',
            'bc.hh_browser_capture_id',
            'capture.candidate_name as browser_candidate_name',
            'capture.original_url as browser_original_url',
            'capture.candidate_resume_url as browser_candidate_resume_url',
            'capture.payload as browser_payload',
            'capture.resume_structured as browser_resume_structured',
            'capture.raw_text as browser_raw_text',
        ];

        $negotiations = $perPage === 0
            ? $this->allRowsPaginator($query->get($columns)->map(fn (object $negotiation): object => $this->hydrateDisplayCandidate($negotiation)), $request)
            : $query
                ->paginate($perPage, $columns)
                ->withQueryString()
                ->through(fn (object $negotiation): object => $this->hydrateDisplayCandidate($negotiation));
        $nextPage = $negotiations->hasMorePages() ? $negotiations->currentPage() + 1 : null;

        if ($request->ajax()) {
            return response()->json([
                'html' => view('hh-resumes.partials.rows', [
                    'negotiations' => $negotiations,
                ])->render(),
                'loader_html' => view('hh-resumes.partials.loader-row', [
                    'nextPage' => $nextPage,
                    'tableColspan' => 6,
                ])->render(),
                'has_more' => $negotiations->hasMorePages(),
                'next_page' => $nextPage,
            ]);
        }

        $vacancies = DB::table('legal.hh_vacancies')
            ->orderByDesc('last_synced_at')
            ->limit(20)
            ->get();

        $latestAnalysisBatch = DB::table('legal.hh_resume_analysis_batches')
            ->when($vacancyId !== '', fn ($query) => $query->where('hh_vacancy_id', $vacancyId))
            ->orderByDesc('hh_resume_analysis_batch_id')
            ->first();

        return view('hh-resumes.index', [
            'credential' => $credential,
            'vacancyId' => $vacancyId,
            'perPage' => $perPage,
            'summary' => $summary,
            'vacancies' => $vacancies,
            'negotiations' => $negotiations,
            'nextPage' => $nextPage,
            'latestAnalysisBatch' => $latestAnalysisBatch,
        ]);
    }

    private function perPage(Request $request): int
    {
        return UserUiSettings::paginationRows($request, 'hh-resumes-rows', 100);
    }

    private function allRowsPaginator(\Illuminate\Support\Collection $rows, Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $rows,
            $rows->count(),
            max(1, $rows->count()),
            1,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    private function hydrateDisplayCandidate(object $negotiation): object
    {
        $raw = $this->jsonArray($negotiation->raw ?? null);
        $resumeRaw = $this->jsonArray($negotiation->resume_raw ?? null);
        $browserResume = $this->jsonArray($negotiation->browser_resume_structured ?? null);
        $browserPayload = $this->jsonArray($negotiation->browser_payload ?? null);

        $negotiation->display_candidate_name = $this->candidateNameFromRawText($negotiation->browser_raw_text ?? null)
            ?? $this->usableCandidateName($negotiation->candidate_name ?? null)
            ?? $this->usableCandidateName($negotiation->browser_candidate_name ?? null)
            ?? $this->usableCandidateName(data_get($browserResume, 'name'))
            ?? $this->usableCandidateName(data_get($browserResume, 'candidate.name'))
            ?? $this->usableCandidateName(data_get($browserPayload, 'candidate.name'))
            ?? 'Кандидат без имени';

        $negotiation->display_candidate_photo = data_get($resumeRaw, 'photo.small')
            ?: data_get($resumeRaw, 'photo.100')
            ?: data_get($resumeRaw, 'photo.40')
            ?: data_get($resumeRaw, 'photo.medium')
            ?: data_get($resumeRaw, 'photo.500')
            ?: data_get($browserResume, 'photo')
            ?: data_get($browserResume, 'photo.small')
            ?: data_get($browserResume, 'photo.100')
            ?: data_get($browserResume, 'photo.40')
            ?: data_get($browserResume, 'avatar')
            ?: data_get($browserResume, 'image')
            ?: data_get($browserResume, 'candidate.photo')
            ?: data_get($browserPayload, 'candidate.photo')
            ?: data_get($browserPayload, 'resumeStructured.photo')
            ?: data_get($resumeRaw, 'browser_capture.resumeStructured.photo')
            ?: data_get($resumeRaw, 'browser_capture.browser_capture.resumeStructured.photo');

        $negotiation->display_resume_id = $this->numericResumeIdFromUrls([
            $negotiation->browser_original_url ?? null,
            $negotiation->browser_candidate_resume_url ?? null,
            $negotiation->alternate_url ?? null,
            $negotiation->resume_url ?? null,
            data_get($browserPayload, 'candidate.resumeUrl'),
            data_get($browserPayload, 'page.url'),
            data_get($browserPayload, 'page.originalUrl'),
            data_get($browserResume, 'originalUrl'),
            data_get($resumeRaw, 'alternate_url'),
            data_get($resumeRaw, 'browser_capture.candidate.resumeUrl'),
            data_get($resumeRaw, 'browser_capture.page.url'),
            data_get($resumeRaw, 'browser_capture.page.originalUrl'),
        ]) ?? $negotiation->resume_id;

        $negotiation->display_cover_letter = $this->usableCoverLetter(data_get($browserResume, 'response.coverLetter'))
            ?? $this->usableCoverLetter(data_get($browserPayload, 'response.coverLetter'))
            ?? $this->usableCoverLetter(data_get($browserPayload, 'browser_capture.response.coverLetter'))
            ?? $this->usableCoverLetter(data_get($raw, 'response.coverLetter'))
            ?? $this->usableCoverLetter(data_get($raw, 'browser_capture.response.coverLetter'))
            ?? $this->usableCoverLetter(data_get($resumeRaw, 'response.coverLetter'))
            ?? $this->usableCoverLetter(data_get($resumeRaw, 'browser_capture.response.coverLetter'))
            ?? $this->usableCoverLetter(data_get($resumeRaw, 'browser_capture.browser_capture.response.coverLetter'));

        return $negotiation;
    }

    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function usableCandidateName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($value === '' || mb_strtolower($value) === 'посмотреть') {
            return null;
        }

        if (str_contains(mb_strtolower($value), 'з/п')) {
            return null;
        }

        return $value;
    }

    private function usableCoverLetter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
        $value = preg_replace("/\n{3,}/u", "\n\n", $value) ?? $value;
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param list<mixed> $urls
     */
    private function numericResumeIdFromUrls(array $urls): ?string
    {
        foreach ($urls as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            $query = parse_url($url, PHP_URL_QUERY);
            parse_str(is_string($query) ? $query : '', $params);

            $resumeId = $params['resumeId'] ?? $params['resume_id'] ?? null;

            if (is_scalar($resumeId) && preg_match('/^\d+$/', (string) $resumeId) === 1) {
                return (string) $resumeId;
            }
        }

        return null;
    }

    private function candidateNameFromRawText(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $lines = array_values(array_filter(array_map(
            fn (string $line): string => trim($line),
            preg_split('/\R/u', $value) ?: [],
        ), fn (string $line): bool => $line !== ''));

        foreach ($lines as $index => $line) {
            if ($line !== 'Отправить нанимающему') {
                continue;
            }

            for ($offset = $index + 1; $offset < min(count($lines), $index + 16); $offset++) {
                $candidate = $this->usableCandidateName($lines[$offset]);

                if ($candidate !== null && preg_match('/^[\p{Lu}Ё][\p{L}ёЁ-]+(?:\s+[\p{Lu}Ё][\p{L}ёЁ-]+){1,3}$/u', $candidate) === 1) {
                    return $candidate;
                }
            }
        }

        return null;
    }
    public function redirect(Request $request, HhApiClient $client): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $state = Str::random(40);
        $request->session()->put('hh_oauth_state', $state);

        return redirect()->away($client->authorizationUrl($state));
    }

    public function callback(Request $request, HhApiClient $client): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $state = (string) $request->query('state', '');

        if ($state === '' || $state !== (string) $request->session()->pull('hh_oauth_state')) {
            return redirect()->route('hh-resumes.index')->with('error', 'HH OAuth state не совпал. Попробуйте подключить HH еще раз.');
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return redirect()->route('hh-resumes.index')->with('error', 'HH не вернул authorization code.');
        }

        try {
            $payload = $client->exchangeCode($code);
            $client->storeTokenForUser((int) $request->user()->getKey(), $payload);
        } catch (Throwable $exception) {
            return redirect()->route('hh-resumes.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('hh-resumes.index')->with('status', 'HH подключен. Теперь можно синхронизировать отклики по ID вакансии.');
    }

    public function sync(Request $request, HhResumeSyncService $service): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'vacancy_id' => ['required', 'string', 'max:50'],
        ]);

        $vacancyId = trim((string) $validated['vacancy_id']);

        try {
            $summary = $service->sync($vacancyId, $request->user(), [
                'started_by_type' => 'user',
                'started_by_user_id' => $request->user()->getKey(),
                'started_from' => 'ui',
            ]);
        } catch (Throwable $exception) {
            return redirect()
                ->route('hh-resumes.index', ['vacancy_id' => $vacancyId])
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('hh-resumes.index', ['vacancy_id' => $vacancyId])
            ->with('status', sprintf(
                'HH синхронизация завершена: %d отклик(ов), %d PDF, run #%d.',
                $summary['negotiations'],
                $summary['pdfs'],
                $summary['sync_run_id'],
            ));
    }

    public function analyzeAll(Request $request, HhResumeBatchAnalysisService $service): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'vacancy_id' => ['nullable', 'string', 'max:50'],
        ]);

        $vacancyId = trim((string) ($validated['vacancy_id'] ?? ''));
        $scopeVacancyId = $vacancyId === '' ? null : $vacancyId;

        try {
            $summary = $service->submit($scopeVacancyId, (int) $request->user()->getKey(), 'ui');
        } catch (Throwable $exception) {
            return redirect()
                ->route('hh-resumes.index', array_filter(['vacancy_id' => $vacancyId]))
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('hh-resumes.index', array_filter(['vacancy_id' => $vacancyId]))
            ->with('status', sprintf(
                'Оценка резюме отправлена в OpenAI Batch: %d резюме, batch #%d.',
                $summary['total_count'],
                $summary['batch_id'],
            ));
    }

    public function destroy(Request $request, int $negotiationId): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $negotiation = DB::table('legal.hh_negotiations')
            ->where('hh_negotiation_id', $negotiationId)
            ->first(['hh_negotiation_id', 'hh_vacancy_id']);

        abort_unless($negotiation !== null, 404);

        DB::table('legal.hh_negotiations')
            ->where('hh_negotiation_id', $negotiationId)
            ->delete();

        return redirect()
            ->route('hh-resumes.index', ['vacancy_id' => $negotiation->hh_vacancy_id])
            ->with('status', 'HH резюме удалено из списка.');
    }
}
