<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HhBrowserCapturePageTest extends TestCase
{
    public function test_admin_opens_browser_capture_list_and_detail(): void
    {
        DB::table('legal.hh_browser_captures')
            ->where('dedupe_key', 'test-hh-browser-capture-page')
            ->delete();

        $user = User::query()->updateOrCreate(
            ['email' => 'hh-captures@example.com'],
            [
                'name' => 'HH Admin',
                'password' => 'secret',
                'is_admin' => true,
                'is_active' => true,
            ],
        );

        $captureId = DB::table('legal.hh_browser_captures')->insertGetId([
            'dedupe_key' => 'test-hh-browser-capture-page',
            'source' => 'test',
            'page_url' => 'https://hh.ru/resume/test?vacancyId=134293071&resumeId=260541190',
            'original_url' => 'https://hh.ru/resume/original?vacancyId=134293071&resumeId=260541190',
            'page_title' => 'PHP Backend Developer',
            'hh_vacancy_id' => '134293071',
            'vacancy_title' => 'PHP backend',
            'resume_id' => '260541190',
            'candidate_name' => 'Test Candidate',
            'candidate_resume_url' => 'https://hh.ru/resume/test?vacancyId=134293071&resumeId=260541190',
            'raw_text' => "Full resume text\nLaravel PostgreSQL",
            'raw_links' => json_encode([], JSON_THROW_ON_ERROR),
            'resume_structured' => json_encode([
                'title' => 'PHP Backend Developer',
                'skills' => ['Laravel', 'PostgreSQL'],
                'experience' => [[
                    'company' => '365 Media Group',
                    'position' => 'PHP Backend Developer',
                    'period' => 'November 2025 - now',
                ]],
                'sections' => [
                    ['heading' => 'Experience', 'text' => 'Built ERP systems'],
                ],
            ], JSON_THROW_ON_ERROR),
            'payload' => json_encode(['source' => 'test'], JSON_THROW_ON_ERROR),
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'hh_browser_capture_id');

        $this->actingAs($user)
            ->get(route('hh-browser-captures.index'))
            ->assertOk()
            ->assertSee('Test Candidate')
            ->assertSee(route('hh-browser-captures.show', $captureId));

        $this->actingAs($user)
            ->get(route('hh-browser-captures.show', $captureId))
            ->assertOk()
            ->assertSee('PHP Backend Developer')
            ->assertSee('365 Media Group')
            ->assertSee('https://hh.ru/resume/original?vacancyId=134293071&amp;resumeId=260541190', false)
            ->assertSee('Full resume text');
    }
}
