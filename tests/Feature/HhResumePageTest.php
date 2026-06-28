<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HhResumePageTest extends TestCase
{
    public function test_admin_opens_hh_resumes_page(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'hh-resumes-page@example.com'],
            [
                'name' => 'HH Resumes Admin',
                'password' => 'secret',
                'is_admin' => true,
                'is_active' => true,
            ],
        );

        $this->actingAs($user)
            ->get(route('hh-resumes.index'))
            ->assertOk()
            ->assertSee('HH');
    }

    public function test_admin_sees_candidate_photo_and_double_click_internal_capture_detail(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'hh-resumes-photo@example.com'],
            [
                'name' => 'HH Resumes Photo Admin',
                'password' => 'secret',
                'is_admin' => true,
                'is_active' => true,
            ],
        );

        DB::table('legal.hh_browser_captures')
            ->where('dedupe_key', 'test-hh-resumes-page-capture')
            ->delete();

        DB::table('legal.hh_vacancies')->updateOrInsert(
            ['hh_vacancy_id' => 'photo-test-vacancy'],
            [
                'name' => 'Photo Test Vacancy',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('legal.hh_negotiations')->updateOrInsert(
            [
                'hh_vacancy_id' => 'photo-test-vacancy',
                'resume_id' => 'photo-test-resume',
            ],
            [
                'candidate_name' => null,
                'resume_title' => 'Laravel разработчик',
                'alternate_url' => 'https://hh.ru/applicant/negotiations/response-test',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'resume_raw' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $captureId = DB::table('legal.hh_browser_captures')->insertGetId([
            'dedupe_key' => 'test-hh-resumes-page-capture',
            'source' => 'test',
            'page_url' => 'https://hh.ru/resume/photo-test?vacancyId=photo-test-vacancy&resumeId=photo-test-resume',
            'original_url' => 'https://hh.ru/resume/photo-test?vacancyId=photo-test-vacancy&resumeId=photo-test-resume',
            'page_title' => 'Laravel разработчик',
            'hh_vacancy_id' => 'photo-test-vacancy',
            'vacancy_title' => 'Photo Test Vacancy',
            'resume_id' => 'photo-test-resume',
            'candidate_name' => 'Посмотреть',
            'candidate_resume_url' => 'https://hh.ru/resume/photo-test?vacancyId=photo-test-vacancy&resumeId=photo-test-resume',
            'raw_text' => "Отправить нанимающему
Иван Петров
Мужчина, 30 лет",
            'raw_links' => json_encode([], JSON_THROW_ON_ERROR),
            'resume_structured' => json_encode([], JSON_THROW_ON_ERROR),
            'payload' => json_encode([
                'source' => 'test',
                'candidate' => [
                    'photo' => 'https://img.hhcdn.ru/photo-test.jpg',
                ],
            ], JSON_THROW_ON_ERROR),
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'hh_browser_capture_id');

        $this->actingAs($user)
            ->get(route('hh-resumes.index', ['vacancy_id' => 'photo-test-vacancy']))
            ->assertOk()
            ->assertSee('Иван Петров')
            ->assertSee('src="https://img.hhcdn.ru/photo-test.jpg"', false)
            ->assertSee('vacancyId: photo-test-vacancy')
            ->assertSee('resumeId: photo-test-resume')
            ->assertSee('Всего:')
            ->assertSee('Сохраненных:')
            ->assertSee(route('hh-browser-captures.show', $captureId), false)
            ->assertSee('ondblclick="window.location.href = this.dataset.href"', false)
            ->assertDontSee('ondblclick="window.open', false);
    }

    public function test_admin_deletes_hh_resume_from_row_menu(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'hh-resumes-delete@example.com'],
            [
                'name' => 'HH Resumes Delete Admin',
                'password' => 'secret',
                'is_admin' => true,
                'is_active' => true,
            ],
        );

        DB::table('legal.hh_vacancies')->updateOrInsert(
            ['hh_vacancy_id' => 'delete-test-vacancy'],
            [
                'name' => 'Delete Test Vacancy',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('legal.hh_negotiations')->updateOrInsert(
            [
                'hh_vacancy_id' => 'delete-test-vacancy',
                'resume_id' => 'delete-test-resume',
            ],
            [
                'candidate_name' => 'Удаляемый Кандидат',
                'resume_title' => 'PHP разработчик',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'resume_raw' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $negotiationId = (int) DB::table('legal.hh_negotiations')
            ->where('hh_vacancy_id', 'delete-test-vacancy')
            ->where('resume_id', 'delete-test-resume')
            ->value('hh_negotiation_id');

        $this->actingAs($user)
            ->get(route('hh-resumes.index', ['vacancy_id' => 'delete-test-vacancy']))
            ->assertOk()
            ->assertSee('Удаляемый Кандидат')
            ->assertSee(route('hh-resumes.destroy', $negotiationId), false)
            ->assertSee('Удалить');

        $this->actingAs($user)
            ->delete(route('hh-resumes.destroy', $negotiationId))
            ->assertRedirect(route('hh-resumes.index', ['vacancy_id' => 'delete-test-vacancy']));

        $this->assertDatabaseMissing('legal.hh_negotiations', [
            'hh_negotiation_id' => $negotiationId,
        ]);
    }
}
