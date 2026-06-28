<?php

namespace App\Http\Controllers;

use App\Services\Hh\HhApiClient;
use App\Services\Hh\HhResumeSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class HhResumeController extends Controller
{
    public function index(Request $request, HhApiClient $client): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $vacancyId = trim((string) $request->query('vacancy_id', ''));
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
            ->orderByDesc('n.analysis_score')
            ->orderByDesc('n.responded_at');

        if ($vacancyId !== '') {
            $query->where('n.hh_vacancy_id', $vacancyId);
        }

        $negotiations = $query
            ->limit(100)
            ->get([
                'n.*',
                'v.name as vacancy_name',
                'bc.hh_browser_capture_id',
                'capture.candidate_name as browser_candidate_name',
                'capture.payload as browser_payload',
                'capture.resume_structured as browser_resume_structured',
                'capture.raw_text as browser_raw_text',
            ])
            ->map(fn (object $negotiation): object => $this->hydrateDisplayCandidate($negotiation));

        $vacancies = DB::table('legal.hh_vacancies')
            ->orderByDesc('last_synced_at')
            ->limit(20)
            ->get();

        return view('hh-resumes.index', [
            'credential' => $credential,
            'vacancyId' => $vacancyId,
            'vacancies' => $vacancies,
            'negotiations' => $negotiations,
        ]);
    }

    private function hydrateDisplayCandidate(object $negotiation): object
    {
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
}
