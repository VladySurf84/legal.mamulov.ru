<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HhBrowserCaptureApiTest extends TestCase
{
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
