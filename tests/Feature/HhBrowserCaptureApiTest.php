<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HhBrowserCaptureApiTest extends TestCase
{
    public function test_browser_capture_stores_structured_resume_and_candidate_photo(): void
    {
        Config::set('services.hh.browser_capture_token', 'test-token');

        DB::table('legal.hh_negotiations')
            ->where('hh_vacancy_id', '999002')
            ->where('resume_id', 'structured-resume')
            ->delete();
        DB::table('legal.hh_browser_captures')
            ->where('page_url', 'https://hh.ru/resume/test-hash?vacancyId=999002&resumeId=structured-resume')
            ->delete();
        DB::table('legal.hh_vacancies')
            ->where('hh_vacancy_id', '999002')
            ->delete();

        $response = $this
            ->withHeader('X-HH-Capture-Token', 'test-token')
            ->postJson(route('api.hh.browser-captures.store'), [
                'source' => 'hh-browser-extension',
                'capturedAt' => '2026-06-28T15:30:00.000Z',
                'page' => [
                    'url' => 'https://hh.ru/resume/test-hash?vacancyId=999002&resumeId=structured-resume',
                    'title' => 'PHP Backend Developer',
                ],
                'candidate' => [
                    'name' => 'Andrey Candidate',
                    'resumeUrl' => 'https://hh.ru/resume/test-hash?vacancyId=999002&resumeId=structured-resume',
                    'photo' => 'https://hh.ru/photo/768289024.jpeg?t=1782745875&h=test',
                    'gender' => 'Male',
                    'location' => 'Moscow',
                    'age' => '28 years',
                    'birthday' => '12 December 1997',
                    'relocation' => 'Moscow, remote',
                    'lastActivity' => 'Online today',
                    'updatedAtText' => '28.06.2026',
                ],
                'vacancy' => [
                    'id' => '999002',
                    'title' => 'Backend role',
                    'url' => 'https://hh.ru/vacancy/999002',
                ],
                'response' => [
                    'coverLetter' => 'I designed Laravel integrations and migrations.',
                ],
                'resumeStructured' => [
                    'title' => 'PHP Backend Developer',
                    'photo' => 'https://hh.ru/photo/768289024.jpeg?t=1782745875&h=test',
                    'candidate' => [
                        'name' => 'Andrey Candidate',
                        'photo' => 'https://hh.ru/photo/768289024.jpeg?t=1782745875&h=test',
                    ],
                    'employment' => 'Full time',
                    'schedule' => 'Remote',
                    'skills' => ['PHP', 'Laravel', 'PostgreSQL'],
                    'experience' => [[
                        'company' => '365 Media Group',
                        'duration' => '8 months',
                        'period' => 'November 2025 - now',
                        'position' => 'PHP Backend Developer',
                        'description' => 'Laravel, MySQL, RabbitMQ.',
                    ]],
                    'response' => [
                        'coverLetter' => 'I designed Laravel integrations and migrations.',
                    ],
                ],
                'raw' => [
                    'text' => 'Full resume text',
                    'links' => [],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('vacancy_id', '999002')
            ->assertJsonPath('resume_id', 'structured-resume');

        $capture = DB::table('legal.hh_browser_captures')
            ->where('page_url', 'https://hh.ru/resume/test-hash?vacancyId=999002&resumeId=structured-resume')
            ->first();

        $this->assertSame('https://hh.ru/photo/768289024.jpeg?t=1782745875&h=test', data_get(json_decode((string) $capture->payload, true), 'candidate.photo'));
        $this->assertSame('365 Media Group', data_get(json_decode((string) $capture->resume_structured, true), 'experience.0.company'));

        $resumeRaw = DB::table('legal.hh_negotiations')
            ->where('hh_vacancy_id', '999002')
            ->where('resume_id', 'structured-resume')
            ->value('resume_raw');

        $this->assertSame('https://hh.ru/photo/768289024.jpeg?t=1782745875&h=test', data_get(json_decode((string) $resumeRaw, true), 'browser_capture.candidate.photo'));
    }

    public function test_browser_capture_stores_vacancies_page(): void
    {
        Config::set('services.hh.browser_capture_token', 'test-token');

        DB::table('legal.hh_browser_captures')
            ->where('dedupe_key', hash('sha256', implode('|', [
                '999001',
                'https://hh.ru/vacancy/999001?hhtmFrom=employer_vacancies',
                'https://hh.ru/employer/vacancies',
            ])))
            ->delete();
        DB::table('legal.hh_vacancies')
            ->where('hh_vacancy_id', '999001')
            ->delete();

        $response = $this
            ->withHeader('X-HH-Capture-Token', 'test-token')
            ->postJson(route('api.hh.browser-captures.store'), [
                'source' => 'hh-browser-extension-vacancies',
                'capturedAt' => '2026-06-28T15:00:00.000Z',
                'page' => [
                    'url' => 'https://hh.ru/employer/vacancies',
                    'title' => 'Вакансии',
                ],
                'vacancy' => [
                    'id' => '999001',
                    'title' => 'Senior PHP/Laravel разработчик для ERP-проектов',
                    'url' => 'https://hh.ru/vacancy/999001?hhtmFrom=employer_vacancies',
                ],
                'vacancies' => [[
                    'id' => '999001',
                    'title' => 'Senior PHP/Laravel разработчик для ERP-проектов',
                    'url' => 'https://hh.ru/vacancy/999001?hhtmFrom=employer_vacancies',
                    'status' => 'active',
                    'publicationType' => 'Стандарт',
                    'area' => 'Москва',
                    'views' => 1367,
                    'responses' => [
                        'total' => 508,
                        'new' => 378,
                        'url' => 'https://hh.ru/employer/vacancyresponses?vacancyId=999001',
                    ],
                    'calls' => [
                        'total' => 10,
                        'url' => 'https://hh.ru/calls-history?vacancyId=999001',
                    ],
                    'resumesInProgress' => 6,
                    'suitableResumes' => [
                        'total' => 18840,
                        'url' => 'https://hh.ru/search/resume?vacancy_id=999001',
                    ],
                    'expiresAtText' => '18.07',
                    'rawText' => 'Senior PHP/Laravel разработчик для ERP-проектов 508 +378 откликов',
                ]],
                'vacanciesStructured' => [
                    'total' => 1,
                ],
                'raw' => [
                    'text' => 'Вакансии Senior PHP/Laravel разработчик для ERP-проектов',
                    'links' => [],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('vacancy_id', '999001')
            ->assertJsonPath('resume_id', null);

        $this->assertDatabaseHas('legal.hh_vacancies', [
            'hh_vacancy_id' => '999001',
            'name' => 'Senior PHP/Laravel разработчик для ERP-проектов',
            'alternate_url' => 'https://hh.ru/vacancy/999001?hhtmFrom=employer_vacancies',
        ]);

        $raw = DB::table('legal.hh_vacancies')
            ->where('hh_vacancy_id', '999001')
            ->value('raw');

        $this->assertSame(508, data_get(json_decode((string) $raw, true), 'responses.total'));
    }
}
