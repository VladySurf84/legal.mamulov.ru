<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserUiSetting;
use App\Support\UserAccess;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    public function test_non_admin_can_view_hh_resumes_and_detail_with_hh_resumes_permission(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'hh-resumes-viewer@example.com'],
            [
                'name' => 'HH Resumes Viewer',
                'password' => 'secret',
                'is_admin' => false,
                'is_active' => true,
            ],
        );
        DB::table('legal.user_module_permissions')
            ->where('user_id', $user->getKey())
            ->delete();

        DB::table('legal.hh_browser_captures')
            ->where('dedupe_key', 'test-hh-resumes-permission-capture')
            ->delete();

        DB::table('legal.hh_vacancies')->updateOrInsert(
            ['hh_vacancy_id' => 'permission-test-vacancy'],
            [
                'name' => 'Permission Test Vacancy',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('legal.hh_negotiations')->updateOrInsert(
            [
                'hh_vacancy_id' => 'permission-test-vacancy',
                'resume_id' => 'permission-test-resume',
            ],
            [
                'candidate_name' => 'Permission Candidate',
                'resume_title' => 'PHP developer',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'resume_raw' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $captureId = DB::table('legal.hh_browser_captures')->insertGetId([
            'dedupe_key' => 'test-hh-resumes-permission-capture',
            'source' => 'test',
            'page_url' => 'https://hh.ru/resume/permission-test?vacancyId=permission-test-vacancy&resumeId=permission-test-resume',
            'original_url' => 'https://hh.ru/resume/permission-test?vacancyId=permission-test-vacancy&resumeId=permission-test-resume',
            'page_title' => 'PHP developer',
            'hh_vacancy_id' => 'permission-test-vacancy',
            'vacancy_title' => 'Permission Test Vacancy',
            'resume_id' => 'permission-test-resume',
            'candidate_name' => 'Permission Candidate',
            'candidate_resume_url' => 'https://hh.ru/resume/permission-test?vacancyId=permission-test-vacancy&resumeId=permission-test-resume',
            'raw_text' => 'Permission resume text',
            'raw_links' => json_encode([], JSON_THROW_ON_ERROR),
            'resume_structured' => json_encode([
                'response' => ['coverLetter' => 'Permission cover letter'],
            ], JSON_THROW_ON_ERROR),
            'payload' => json_encode(['source' => 'test'], JSON_THROW_ON_ERROR),
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'hh_browser_capture_id');

        $this->actingAs($user)
            ->get(route('hh-resumes.index', ['vacancy_id' => 'permission-test-vacancy']))
            ->assertForbidden();

        $this->grantGlobalModule($user, UserAccess::MODULE_HH_RESUMES);

        $this->actingAs($user)
            ->get(route('hh-resumes.index', ['vacancy_id' => 'permission-test-vacancy']))
            ->assertOk()
            ->assertSee('Permission Candidate')
            ->assertSee('Permission cover letter')
            ->assertSee(route('hh-browser-captures.show', $captureId), false)
            ->assertSee('ondblclick="window.location.href = this.dataset.href"', false)
            ->assertDontSee(route('hh-resumes.analyze-all'), false)
            ->assertDontSee('data-hh-resume-delete-url', false);
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
            'resume_structured' => json_encode([
                'response' => [
                    'coverLetter' => 'Здравствуйте, хочу обсудить ERP и Laravel PostgreSQL задачи.',
                ],
            ], JSON_THROW_ON_ERROR),
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
            ->assertSee('Сопроводительное письмо')
            ->assertSee('Здравствуйте, хочу обсудить ERP и Laravel PostgreSQL задачи.')
            ->assertSee('data-hh-cover-letter-toggle', false)
            ->assertSee('data-collapsed-label="Полностью"', false)
            ->assertSee('data-expanded-label="Сократить"', false)
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
            ->assertSee('data-hh-resume-context-row', false)
            ->assertSee('data-hh-resume-delete-url="'.route('hh-resumes.destroy', $negotiationId).'"', false)
            ->assertSee('trigger-selector="[data-hh-resume-context-row]"', false)
            ->assertSee('Удалить')
            ->assertDontSee('Действия');

        $this->actingAs($user)
            ->delete(route('hh-resumes.destroy', $negotiationId))
            ->assertRedirect(route('hh-resumes.index', ['vacancy_id' => 'delete-test-vacancy']));

        $this->assertDatabaseMissing('legal.hh_negotiations', [
            'hh_negotiation_id' => $negotiationId,
        ]);
    }

    public function test_resume_id_display_prefers_numeric_query_parameter(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'hh-resumes-numeric-id@example.com'],
            [
                'name' => 'HH Resumes Numeric Id Admin',
                'password' => 'secret',
                'is_admin' => true,
                'is_active' => true,
            ],
        );

        $resumeHash = 'aa8a7877000cd4465300bec22a4c344b4b3156';
        $resumeUrl = 'https://hh.ru/resume/'.$resumeHash;
        $originalUrl = $resumeUrl.'?vacancyId=134293071&t=5389512776&resumeId=215238227&hhtmFromLabel=responses';

        DB::table('legal.hh_browser_captures')
            ->where('dedupe_key', 'test-hh-resumes-numeric-original-url')
            ->delete();

        DB::table('legal.hh_vacancies')->updateOrInsert(
            ['hh_vacancy_id' => '134293071'],
            [
                'name' => 'Numeric Resume Id Vacancy',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('legal.hh_negotiations')->updateOrInsert(
            [
                'hh_vacancy_id' => '134293071',
                'resume_id' => $resumeHash,
            ],
            [
                'candidate_name' => 'Numeric Resume Candidate',
                'resume_title' => 'Backend developer',
                'alternate_url' => $resumeUrl,
                'resume_url' => $resumeUrl,
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'resume_raw' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('legal.hh_browser_captures')->insert([
            'dedupe_key' => 'test-hh-resumes-numeric-original-url',
            'source' => 'test',
            'page_url' => $resumeUrl,
            'original_url' => $originalUrl,
            'page_title' => 'Backend developer',
            'hh_vacancy_id' => '134293071',
            'vacancy_title' => 'Numeric Resume Id Vacancy',
            'resume_id' => $resumeHash,
            'candidate_name' => 'Numeric Resume Candidate',
            'candidate_resume_url' => $resumeUrl,
            'raw_text' => 'Full resume text',
            'raw_links' => json_encode([], JSON_THROW_ON_ERROR),
            'resume_structured' => json_encode([], JSON_THROW_ON_ERROR),
            'payload' => json_encode(['source' => 'test'], JSON_THROW_ON_ERROR),
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('hh-resumes.index', ['vacancy_id' => '134293071']))
            ->assertOk()
            ->assertSee('resumeId: 215238227')
            ->assertDontSee('resumeId: '.$resumeHash);
    }

    public function test_admin_paginates_hh_resumes(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'hh-resumes-pagination@example.com'],
            [
                'name' => 'HH Resumes Pagination Admin',
                'password' => 'secret',
                'is_admin' => true,
                'is_active' => true,
            ],
        );

        DB::table('legal.hh_vacancies')->updateOrInsert(
            ['hh_vacancy_id' => 'pagination-test-vacancy'],
            [
                'name' => 'Pagination Test Vacancy',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        foreach ([
            ['resume_id' => 'pagination-resume-first', 'candidate_name' => 'Pagination First Candidate', 'analysis_score' => 90],
            ['resume_id' => 'pagination-resume-second', 'candidate_name' => 'Pagination Second Candidate', 'analysis_score' => 10],
        ] as $row) {
            DB::table('legal.hh_negotiations')->updateOrInsert(
                [
                    'hh_vacancy_id' => 'pagination-test-vacancy',
                    'resume_id' => $row['resume_id'],
                ],
                [
                    'candidate_name' => $row['candidate_name'],
                    'resume_title' => 'Pagination developer',
                    'analysis_score' => $row['analysis_score'],
                    'raw' => json_encode([], JSON_THROW_ON_ERROR),
                    'resume_raw' => json_encode([], JSON_THROW_ON_ERROR),
                    'responded_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $this->actingAs($user)
            ->get(route('hh-resumes.index', ['vacancy_id' => 'pagination-test-vacancy', 'per_page' => 1]))
            ->assertOk()
            ->assertSee('Pagination First Candidate')
            ->assertDontSee('Pagination Second Candidate')
            ->assertDontSee('Pagination Navigation')
            ->assertSee('data-ui-sticky-table-loader', false)
            ->assertSee('data-next-page="2"', false)
            ->assertSee('page=2', false);

        $this->actingAs($user)
            ->get(route('hh-resumes.index', ['vacancy_id' => 'pagination-test-vacancy', 'per_page' => 1, 'page' => 2]))
            ->assertOk()
            ->assertSee('Pagination Second Candidate')
            ->assertDontSee('Pagination First Candidate');

        $this->actingAs($user)
            ->get(route('hh-resumes.index', ['vacancy_id' => 'pagination-test-vacancy', 'per_page' => 1, 'page' => 2]), [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('next_page', null)
            ->assertJson(fn ($json) => $json
                ->whereType('html', 'string')
                ->whereType('loader_html', 'string')
                ->where('has_more', false)
                ->where('next_page', null)
                ->etc()
            )
            ->assertSee('Pagination Second Candidate', false)
            ->assertDontSee('Pagination First Candidate', false);
    }

    public function test_admin_hh_resumes_pagination_uses_sticky_table_settings(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'hh-resumes-pagination-settings@example.com'],
            [
                'name' => 'HH Resumes Pagination Settings Admin',
                'password' => 'secret',
                'is_admin' => true,
                'is_active' => true,
            ],
        );

        UserUiSetting::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'setting_key' => 'sticky-table:hh-resumes.index:hh-resumes-rows',
            ],
            [
                'settings' => ['paginationRows' => 1],
            ],
        );

        DB::table('legal.hh_vacancies')->updateOrInsert(
            ['hh_vacancy_id' => 'pagination-settings-test-vacancy'],
            [
                'name' => 'Pagination Settings Test Vacancy',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        foreach ([
            ['resume_id' => 'pagination-settings-resume-first', 'candidate_name' => 'Pagination Settings First Candidate', 'analysis_score' => 90],
            ['resume_id' => 'pagination-settings-resume-second', 'candidate_name' => 'Pagination Settings Second Candidate', 'analysis_score' => 10],
        ] as $row) {
            DB::table('legal.hh_negotiations')->updateOrInsert(
                [
                    'hh_vacancy_id' => 'pagination-settings-test-vacancy',
                    'resume_id' => $row['resume_id'],
                ],
                [
                    'candidate_name' => $row['candidate_name'],
                    'resume_title' => 'Pagination settings developer',
                    'analysis_score' => $row['analysis_score'],
                    'raw' => json_encode([], JSON_THROW_ON_ERROR),
                    'resume_raw' => json_encode([], JSON_THROW_ON_ERROR),
                    'responded_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $this->actingAs($user)
            ->get(route('hh-resumes.index', ['vacancy_id' => 'pagination-settings-test-vacancy']))
            ->assertOk()
            ->assertSee('Pagination Settings First Candidate')
            ->assertDontSee('Pagination Settings Second Candidate')
            ->assertSee('id="hh-resumes-rows"', false);
    }

    public function test_admin_sees_analyze_all_button(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'hh-resumes-analysis-button@example.com'],
            [
                'name' => 'HH Resumes Analysis Button Admin',
                'password' => 'secret',
                'is_admin' => true,
                'is_active' => true,
            ],
        );

        $this->actingAs($user)
            ->get(route('hh-resumes.index'))
            ->assertOk()
            ->assertSee(route('hh-resumes.analyze-all'), false)
            ->assertSee('Оценить все')
            ->assertSee('Оценка Codex');
    }

    public function test_admin_submits_hh_resume_analysis_batch(): void
    {
        Config::set('services.openai.api_key', 'sk-test');
        Config::set('services.openai.base_url', 'https://api.openai.test/v1');
        Config::set('services.openai.model', 'gpt-test');

        Http::fake([
            'https://api.openai.test/v1/files' => Http::response(['id' => 'file-test-input'], 200),
            'https://api.openai.test/v1/batches' => Http::response([
                'id' => 'batch-test',
                'status' => 'validating',
            ], 200),
        ]);

        DB::table('legal.hh_resume_analysis_batches')
            ->where('openai_batch_id', 'batch-test')
            ->delete();

        $user = User::query()->updateOrCreate(
            ['email' => 'hh-resumes-analysis-submit@example.com'],
            [
                'name' => 'HH Resumes Analysis Submit Admin',
                'password' => 'secret',
                'is_admin' => true,
                'is_active' => true,
            ],
        );

        DB::table('legal.hh_vacancies')->updateOrInsert(
            ['hh_vacancy_id' => 'analysis-submit-vacancy'],
            [
                'name' => 'Analysis Submit Vacancy',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('legal.hh_negotiations')->updateOrInsert(
            [
                'hh_vacancy_id' => 'analysis-submit-vacancy',
                'resume_id' => 'analysis-submit-resume',
            ],
            [
                'candidate_name' => 'Analysis Submit Candidate',
                'resume_title' => 'Laravel backend developer',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'resume_raw' => json_encode(['skill_set' => ['PHP', 'Laravel']], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->actingAs($user)
            ->post(route('hh-resumes.analyze-all'), ['vacancy_id' => 'analysis-submit-vacancy'])
            ->assertRedirect(route('hh-resumes.index', ['vacancy_id' => 'analysis-submit-vacancy']));

        $this->assertDatabaseHas('legal.hh_resume_analysis_batches', [
            'openai_batch_id' => 'batch-test',
            'input_file_id' => 'file-test-input',
            'hh_vacancy_id' => 'analysis-submit-vacancy',
            'model' => 'gpt-test',
            'total_count' => 1,
            'status' => 'validating',
        ]);

        $batchId = (int) DB::table('legal.hh_resume_analysis_batches')
            ->where('openai_batch_id', 'batch-test')
            ->value('hh_resume_analysis_batch_id');

        foreach ([
            'batch_jsonl_prepared',
            'openai_file_upload_request',
            'openai_file_upload_response',
            'openai_batch_create_request',
            'openai_batch_create_response',
        ] as $eventType) {
            $this->assertDatabaseHas('legal.hh_resume_analysis_batch_logs', [
                'hh_resume_analysis_batch_id' => $batchId,
                'event_type' => $eventType,
            ]);
        }

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.test/v1/files'
            && str_contains($request->body(), 'цель -> правила -> функции -> события -> контроль исполнения -> улучшения')
            && str_contains($request->body(), 'production-логами')
            && str_contains($request->body(), 'legal.mamulov.ru'));
    }

    public function test_poll_hh_resume_analysis_batch_applies_results(): void
    {
        Config::set('services.openai.api_key', 'sk-test');
        Config::set('services.openai.base_url', 'https://api.openai.test/v1');

        DB::table('legal.hh_resume_analysis_batches')
            ->where('openai_batch_id', 'like', 'batch-%test')
            ->delete();

        $user = User::query()->updateOrCreate(
            ['email' => 'hh-resumes-analysis-poll@example.com'],
            [
                'name' => 'HH Resumes Analysis Poll Admin',
                'password' => 'secret',
                'is_admin' => true,
                'is_active' => true,
            ],
        );

        DB::table('legal.hh_vacancies')->updateOrInsert(
            ['hh_vacancy_id' => 'analysis-poll-vacancy'],
            [
                'name' => 'Analysis Poll Vacancy',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('legal.hh_negotiations')->updateOrInsert(
            [
                'hh_vacancy_id' => 'analysis-poll-vacancy',
                'resume_id' => 'analysis-poll-resume',
            ],
            [
                'candidate_name' => 'Analysis Poll Candidate',
                'resume_title' => 'ERP backend developer',
                'raw' => json_encode([], JSON_THROW_ON_ERROR),
                'resume_raw' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $negotiationId = (int) DB::table('legal.hh_negotiations')
            ->where('hh_vacancy_id', 'analysis-poll-vacancy')
            ->where('resume_id', 'analysis-poll-resume')
            ->value('hh_negotiation_id');

        DB::table('legal.hh_resume_analysis_batches')->insert([
            'openai_batch_id' => 'batch-poll-test',
            'input_file_id' => 'file-input-test',
            'status' => 'in_progress',
            'hh_vacancy_id' => 'analysis-poll-vacancy',
            'model' => 'gpt-test',
            'total_count' => 1,
            'requested_by_user_id' => $user->id,
            'requested_from' => 'test',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('legal.hh_resume_analysis_batches')
            ->where('openai_batch_id', 'batch-poll-test')
            ->value('hh_resume_analysis_batch_id');

        $outputLine = json_encode([
            'custom_id' => 'hh_resume:'.$negotiationId,
            'response' => [
                'status_code' => 200,
                'body' => [
                    'output_text' => json_encode([
                        'score' => 87,
                        'summary' => 'Сильный ERP/backend кандидат.',
                        'flags' => [
                            ['label' => 'Laravel и PostgreSQL', 'weight' => 18, 'matched' => true],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        Http::fake([
            'https://api.openai.test/v1/batches/batch-poll-test' => Http::response([
                'id' => 'batch-poll-test',
                'status' => 'completed',
                'output_file_id' => 'file-output-test',
            ], 200),
            'https://api.openai.test/v1/files/file-output-test/content' => Http::response($outputLine."\n", 200),
        ]);

        $this->artisan('hh:poll-resume-analysis')
            ->assertExitCode(0);

        $this->assertDatabaseHas('legal.hh_negotiations', [
            'hh_negotiation_id' => $negotiationId,
            'codex_analysis_score' => 87,
            'codex_analysis_summary' => 'Сильный ERP/backend кандидат.',
        ]);

        $this->assertDatabaseHas('legal.hh_resume_analysis_batches', [
            'openai_batch_id' => 'batch-poll-test',
            'status' => 'completed',
            'processed_count' => 1,
            'failed_count' => 0,
        ]);

        foreach ([
            'openai_get_request',
            'openai_get_response',
            'openai_output_download_request',
            'openai_output_download_response',
            'openai_output_row_applied',
        ] as $eventType) {
            $this->assertDatabaseHas('legal.hh_resume_analysis_batch_logs', [
                'hh_resume_analysis_batch_id' => $batchId,
                'event_type' => $eventType,
            ]);
        }
    }
}
