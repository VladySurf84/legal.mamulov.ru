<?php

namespace App\Http\Controllers;

use App\Support\UserAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HhBrowserCapturePageController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(UserAccess::canViewHhResumes($request->user()), 403);

        $vacancyId = trim((string) $request->query('vacancy_id', ''));
        $search = trim((string) $request->query('q', ''));

        $query = DB::table('legal.hh_browser_captures')
            ->orderByDesc('captured_at')
            ->orderByDesc('hh_browser_capture_id');

        if ($vacancyId !== '') {
            $query->where('hh_vacancy_id', $vacancyId);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($query) use ($like): void {
                $query->where('candidate_name', 'ilike', $like)
                    ->orWhere('vacancy_title', 'ilike', $like)
                    ->orWhere('raw_text', 'ilike', $like)
                    ->orWhere('resume_id', 'ilike', $like);
            });
        }

        $captures = $query
            ->paginate(50)
            ->withQueryString();

        $vacancies = DB::table('legal.hh_browser_captures')
            ->select('hh_vacancy_id', DB::raw('max(vacancy_title) as vacancy_title'), DB::raw('count(*) as captures_count'))
            ->whereNotNull('hh_vacancy_id')
            ->groupBy('hh_vacancy_id')
            ->orderByDesc(DB::raw('max(captured_at)'))
            ->limit(30)
            ->get();

        return view('hh-browser-captures.index', [
            'captures' => $captures,
            'vacancies' => $vacancies,
            'vacancyId' => $vacancyId,
            'search' => $search,
        ]);
    }

    public function show(Request $request, int $captureId): View
    {
        abort_unless(UserAccess::canViewHhResumes($request->user()), 403);

        $capture = DB::table('legal.hh_browser_captures')
            ->where('hh_browser_capture_id', $captureId)
            ->first();

        abort_unless($capture !== null, 404);

        return view('hh-browser-captures.show', [
            'capture' => $capture,
            'structured' => $this->json($capture->resume_structured ?? null),
            'payload' => $this->json($capture->payload ?? null),
            'links' => $this->json($capture->raw_links ?? null),
        ]);
    }

    /** @return array<string, mixed>|array<int, mixed> */
    private function json(mixed $value): array
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
}
