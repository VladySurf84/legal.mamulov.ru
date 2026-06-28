<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Hh\HhBrowserCaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HhBrowserCaptureController extends Controller
{
    public function store(Request $request, HhBrowserCaptureService $service): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:100'],
            'capturedAt' => ['nullable', 'date'],
            'page' => ['required', 'array'],
            'page.url' => ['required', 'url', 'max:4000'],
            'page.originalUrl' => ['nullable', 'url', 'max:4000'],
            'page.title' => ['nullable', 'string', 'max:1000'],
            'candidate' => ['nullable', 'array'],
            'candidate.name' => ['nullable', 'string', 'max:500'],
            'candidate.resumeUrl' => ['nullable', 'url', 'max:4000'],
            'candidate.photo' => ['nullable', 'url', 'max:4000'],
            'candidate.gender' => ['nullable', 'string', 'max:100'],
            'candidate.location' => ['nullable', 'string', 'max:255'],
            'candidate.age' => ['nullable', 'string', 'max:100'],
            'candidate.birthday' => ['nullable', 'string', 'max:100'],
            'candidate.relocation' => ['nullable', 'string', 'max:500'],
            'candidate.lastActivity' => ['nullable', 'string', 'max:255'],
            'candidate.updatedAtText' => ['nullable', 'string', 'max:100'],
            'vacancy' => ['nullable', 'array'],
            'vacancy.id' => ['nullable', 'string', 'max:50'],
            'vacancy.title' => ['nullable', 'string', 'max:500'],
            'vacancy.url' => ['nullable', 'url', 'max:4000'],
            'vacancies' => ['nullable', 'array', 'max:500'],
            'vacancies.*.id' => ['nullable', 'string', 'max:50'],
            'vacancies.*.title' => ['nullable', 'string', 'max:500'],
            'vacancies.*.url' => ['nullable', 'url', 'max:4000'],
            'vacancies.*.status' => ['nullable', 'string', 'max:100'],
            'vacancies.*.publicationType' => ['nullable', 'string', 'max:255'],
            'vacancies.*.area' => ['nullable', 'string', 'max:255'],
            'vacancies.*.views' => ['nullable', 'integer'],
            'vacancies.*.responses' => ['nullable', 'array'],
            'vacancies.*.responses.total' => ['nullable', 'integer'],
            'vacancies.*.responses.new' => ['nullable', 'integer'],
            'vacancies.*.responses.url' => ['nullable', 'url', 'max:4000'],
            'vacancies.*.calls' => ['nullable', 'array'],
            'vacancies.*.calls.total' => ['nullable', 'integer'],
            'vacancies.*.calls.url' => ['nullable', 'url', 'max:4000'],
            'vacancies.*.resumesInProgress' => ['nullable', 'integer'],
            'vacancies.*.suitableResumes' => ['nullable', 'array'],
            'vacancies.*.suitableResumes.total' => ['nullable', 'integer'],
            'vacancies.*.suitableResumes.url' => ['nullable', 'url', 'max:4000'],
            'vacancies.*.expiresAtText' => ['nullable', 'string', 'max:100'],
            'vacancies.*.rawText' => ['nullable', 'string'],
            'vacanciesStructured' => ['nullable', 'array'],
            'response' => ['nullable', 'array'],
            'response.status' => ['nullable', 'string', 'max:255'],
            'response.coverLetter' => ['nullable', 'string'],
            'resumeStructured' => ['nullable', 'array'],
            'raw' => ['nullable', 'array'],
            'raw.text' => ['nullable', 'string'],
            'raw.links' => ['nullable', 'array'],
        ]);

        $result = $service->store($validated, [
            'captured_by_user_id' => $request->user()?->getKey(),
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return response()->json([
            'ok' => true,
            'id' => $result['id'],
            'action' => $result['action'],
            'vacancy_id' => $result['vacancy_id'],
            'resume_id' => $result['resume_id'],
        ], $result['action'] === 'inserted' ? 201 : 200);
    }
    public function lookup(Request $request, HhBrowserCaptureService $service): JsonResponse
    {
        $validated = $request->validate([
            'vacancy_id' => ['nullable', 'string', 'max:50'],
            'resume_ids' => ['required', 'array', 'max:200'],
            'resume_ids.*' => ['required', 'string', 'max:120'],
        ]);

        $downloaded = $service->downloadedResumeIds(
            isset($validated['vacancy_id']) ? (string) $validated['vacancy_id'] : null,
            $validated['resume_ids'],
        );

        return response()->json([
            'ok' => true,
            'downloaded_resume_ids' => $downloaded,
        ]);
    }
}
