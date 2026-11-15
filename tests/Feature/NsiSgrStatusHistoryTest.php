<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Nsi\NsiSgrSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class NsiSgrStatusHistoryTest extends TestCase
{
    use DatabaseTransactions;

    private array $item;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('legal.nsi_sgr_status_history')) {
            (require database_path('migrations/2026_11_16_120000_create_nsi_sgr_status_history.php'))->up();
        }
        $this->item = [
            'id' => (string) Str::uuid(),
            'versionId' => (string) Str::uuid(),
            'updateDateTime' => '2026-11-15T09:30:00Z',
            'data' => [
                'NUMB_DOC' => 'TEST.HISTORY.'.Str::uuid(),
                'STATUS' => ['id' => (string) Str::uuid(), 'name' => 'подписан и действует', 'type' => '1997'],
            ],
        ];
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'get-list-data-total')) {
                return Http::response(['totalCount' => 1, 'byFilterCount' => 1]);
            }
            if (str_contains($request->url(), 'get-view-card-data-on-date')) {
                return Http::response($this->item);
            }

            return Http::response(['value' => [$this->item], 'Count' => 1]);
        });
    }

    public function test_list_import_records_initial_status_changes_and_returns_without_duplicate_observations(): void
    {
        $this->syncList();
        $initial = $this->item['data']['STATUS'];
        $this->item['versionId'] = (string) Str::uuid();
        $this->item['updateDateTime'] = '2026-11-16T12:00:00Z';
        $this->syncList();
        $this->assertCount(1, $this->history());

        $this->item['data']['STATUS'] = ['id' => (string) Str::uuid(), 'name' => 'приостановлен', 'type' => '1997'];
        $this->syncList();
        $this->item['data']['STATUS'] = $initial;
        $this->syncList();

        $history = $this->history();
        $this->assertSame(['подписан и действует', 'приостановлен', 'подписан и действует'], $history->pluck('status_name')->all());
        $this->assertSame(['initial', 'change', 'change'], $history->pluck('observation_kind')->all());
        $this->assertSame('2026-11-16 12:00:00', $history[1]->source_update_date_time);
        $this->assertNotNull($history[1]->observed_at);
        $this->assertSame('приостановлен', json_decode($history[1]->raw_status, true)['name']);
    }

    public function test_detail_import_uses_the_same_history_and_exposes_it_in_the_card(): void
    {
        $this->syncList();
        $this->item['data']['STATUS'] = ['id' => (string) Str::uuid(), 'name' => 'аннулирован / отозван', 'type' => '1997'];
        app(NsiSgrSyncService::class)->syncDetailRecord($this->recordId(), ['pause_ms' => 0, 'max_retries' => 0]);
        app(NsiSgrSyncService::class)->syncDetailRecord($this->recordId(), ['pause_ms' => 0, 'max_retries' => 0]);
        $this->assertCount(2, $this->history());
        $this->assertSame('detail', $this->history()[1]->source_kind);
        $this->assertSame($this->item['versionId'], $this->history()[1]->source_version_id);

        $response = $this->actingAs(new User(['id' => 1, 'is_admin' => true, 'is_active' => true]))
            ->getJson(route('nsi-sgr.show', ['recordId' => $this->recordId()]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $this->assertStringContainsString('История статусов', $response->json('html'));
        $this->assertStringContainsString('подписан и действует', $response->json('html'));
        $this->assertStringContainsString('аннулирован / отозван', $response->json('html'));
    }

    public function test_existing_record_preserves_baseline_before_first_status_change(): void
    {
        $this->syncList();
        DB::table('legal.nsi_sgr_status_history')->where('nsi_sgr_record_id', $this->recordId())->delete();
        $previousObservedAt = DB::table('legal.nsi_sgr_records')->where('nsi_sgr_record_id', $this->recordId())->value('list_synced_at');
        $this->item['data']['STATUS'] = ['id' => (string) Str::uuid(), 'name' => 'приостановлен', 'type' => '1997'];
        $this->syncList();

        $history = $this->history();
        $this->assertSame(['baseline', 'change'], $history->pluck('observation_kind')->all());
        $this->assertSame('подписан и действует', $history[0]->status_name);
        $this->assertSame(Carbon::parse($previousObservedAt, 'UTC')->timestamp, Carbon::parse($history[0]->observed_at)->timestamp);
    }

    public function test_missing_status_is_recorded_and_repeat_does_not_duplicate_it(): void
    {
        $this->syncList();
        unset($this->item['data']['STATUS']);
        $this->syncList();
        $this->syncList();
        $this->assertCount(2, $this->history());
        $this->assertNull($this->history()[1]->status_name);
    }

    public function test_history_and_current_status_roll_back_together(): void
    {
        $this->syncList();
        DB::beginTransaction();
        DB::table('legal.nsi_sgr_records')->where('nsi_sgr_record_id', $this->recordId())->update(['status_name' => 'приостановлен']);
        $this->assertCount(2, $this->history());
        DB::rollBack();
        $this->assertCount(1, $this->history());
        $this->assertSame('подписан и действует', DB::table('legal.nsi_sgr_records')->where('nsi_sgr_record_id', $this->recordId())->value('status_name'));
    }

    private function syncList(): void
    {
        app(NsiSgrSyncService::class)->syncList(['reset' => true, 'limit' => 1, 'max_pages' => 1, 'pause_ms' => 0, 'max_retries' => 0]);
    }

    private function recordId(): int
    {
        return (int) DB::table('legal.nsi_sgr_records')->where('sgr_number', $this->item['data']['NUMB_DOC'])->value('nsi_sgr_record_id');
    }

    private function history(): \Illuminate\Support\Collection
    {
        return DB::table('legal.nsi_sgr_status_history')->where('nsi_sgr_record_id', $this->recordId())->orderBy('nsi_sgr_status_history_id')->get();
    }
}
