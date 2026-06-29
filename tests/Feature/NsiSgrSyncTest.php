<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NsiSgrSyncTest extends TestCase
{
    public function test_sync_list_stores_sgr_rows(): void
    {
        DB::table('legal.nsi_sgr_records')
            ->where('sgr_number', 'BY.60.61.01.008.E.000040.06.26')
            ->delete();
        DB::table('legal.nsi_sgr_import_state')
            ->where('state_key', 'list')
            ->delete();

        Http::fake([
            'https://nsi.eaeunion.org/portal/api/dictionaries/1995/get-list-data-total' => Http::response([
                'totalCount' => 1,
                'byFilterCount' => 1,
                'updateDate' => '2026-06-26',
                'data' => null,
            ], 200),
            'https://nsi.eaeunion.org/portal/api/dictionaries/1995/get-list-data' => Http::response([
                'value' => [
                    [
                        'id' => '8e33af80-b7c5-481b-acbf-10628ec370a3',
                        'versionId' => 'c537bda6-a20d-4778-b007-6e8a1d2c0da0',
                        'dateTimeFrom' => '2026-06-26T00:00:00.000Z',
                        'dateTimeTo' => '2100-01-01T00:00:00.000Z',
                        'updateDateTime' => '2026-06-26T18:03:54.055Z',
                        'data' => [
                            'NUMB_DOC' => 'BY.60.61.01.008.E.000040.06.26',
                            'STATUS' => [
                                'id' => '0888035a-52fa-4e7e-bf59-348c6cc218d4',
                                'name' => 'подписан и действует',
                                'type' => '1997',
                            ],
                            'SERIALNUMB' => '0035979',
                            'DATE_DOC' => '2026-06-25',
                            'NAME_PROD' => 'Эмульгатор AVI CON EMU',
                            'FIRMMADE_NAME' => 'Vertexco NV',
                            'FIRMGET_NAME' => 'ООО "Текстильное дело"',
                            'DOC_USEAREA' => 'Промышленное использование. Эмульгатор для замасливателя',
                        ],
                        'dateFrom' => '2026-06-26',
                        'dateTo' => '2100-01-01',
                    ],
                ],
                'Count' => 1,
            ], 200),
        ]);

        $this->artisan('nsi:sgr-sync', [
            '--mode' => 'list',
            '--date' => '2026-06-29',
            '--limit' => 1,
            '--max-pages' => 1,
            '--pause-ms' => 0,
            '--error-pause-ms' => 0,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('legal.nsi_sgr_records', [
            'sgr_number' => 'BY.60.61.01.008.E.000040.06.26',
            'status_name' => 'подписан и действует',
            'product_name' => 'Эмульгатор AVI CON EMU',
            'manufacturer_name' => 'Vertexco NV',
        ]);

        $this->assertDatabaseHas('legal.nsi_sgr_import_state', [
            'state_key' => 'list',
            'next_offset' => 1,
            'total_count' => 1,
        ]);
    }

    public function test_sync_details_updates_full_card_fields(): void
    {
        DB::table('legal.nsi_sgr_records')
            ->where('sgr_number', 'BY.60.61.01.008.E.000040.06.26')
            ->delete();

        DB::table('legal.nsi_sgr_records')->insert([
            'nsi_id' => '8e33af80-b7c5-481b-acbf-10628ec370a3',
            'version_id' => 'c537bda6-a20d-4778-b007-6e8a1d2c0da0',
            'sgr_number' => 'BY.60.61.01.008.E.000040.06.26',
            'source_list_payload' => json_encode([], JSON_THROW_ON_ERROR),
            'list_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://nsi.eaeunion.org/portal/api/dictionaries/1995/get-view-card-data-on-date*' => Http::response([
                'id' => '8e33af80-b7c5-481b-acbf-10628ec370a3',
                'versionId' => 'c537bda6-a20d-4778-b007-6e8a1d2c0da0',
                'dateTimeFrom' => '2026-06-26T00:00:00.000Z',
                'dateTimeTo' => '2100-01-01T00:00:00.000Z',
                'updateDateTime' => '2026-06-26T18:03:54.055Z',
                'data' => [
                    'NUMB_DOC' => 'BY.60.61.01.008.E.000040.06.26',
                    'STATUS' => [
                        'id' => '0888035a-52fa-4e7e-bf59-348c6cc218d4',
                        'name' => 'подписан и действует',
                        'type' => '1997',
                    ],
                    'SERIALNUMB' => '0035979',
                    'DATE_DOC' => '2026-06-25',
                    'OKP_PROD' => '0080012',
                    'NAME_PROD' => 'Эмульгатор AVI CON EMU',
                    'PROD_APP' => 'Эмульгатор AVI CON EMU',
                    'FIRMMADE_NAME' => 'Vertexco NV',
                    'FIRMMADE_ADDR' => 'Belgium, B-8930 Menen, Industrielaan 104',
                    'FIRMGET_NAME' => 'ООО "Текстильное дело"',
                    'FIRMGET_INN' => '791309377',
                    'FIRMGET_ADDR' => '212030, г. Могилев, ул. Тимирязевская, д.44',
                    'DOC_USEAREA' => 'Промышленное использование. Эмульгатор для замасливателя',
                    'WHO' => 'М.Н.Сакович',
                    'N_ALFA_CODE' => 'BY',
                    'N_ALFA_NAME' => ['name' => 'БЕЛАРУСЬ'],
                ],
                'dateFrom' => '2026-06-26',
                'dateTo' => '2100-01-01',
            ], 200),
        ]);

        $this->artisan('nsi:sgr-sync', [
            '--mode' => 'details',
            '--date' => '2026-06-29',
            '--number' => 'BY.60.61.01.008.E.000040.06.26',
            '--detail-limit' => 1,
            '--pause-ms' => 0,
            '--error-pause-ms' => 0,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('legal.nsi_sgr_records', [
            'sgr_number' => 'BY.60.61.01.008.E.000040.06.26',
            'recipient_inn' => '791309377',
            'country_code' => 'BY',
            'country_name' => 'БЕЛАРУСЬ',
            'signer_name' => 'М.Н.Сакович',
        ]);
    }

    public function test_nsi_sgr_page_requires_permission_and_shows_records(): void
    {
        DB::table('legal.nsi_sgr_records')
            ->where('sgr_number', 'BY.60.61.01.008.E.000040.06.26')
            ->delete();

        DB::table('legal.nsi_sgr_records')->insert([
            'nsi_id' => '8e33af80-b7c5-481b-acbf-10628ec370a3',
            'sgr_number' => 'BY.60.61.01.008.E.000040.06.26',
            'status_name' => 'подписан и действует',
            'product_name' => 'Эмульгатор AVI CON EMU',
            'manufacturer_name' => 'Vertexco NV',
            'source_list_payload' => json_encode([], JSON_THROW_ON_ERROR),
            'list_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->updateOrCreate(
            ['email' => 'nsi-sgr-viewer@example.com'],
            [
                'name' => 'NSI SGR Viewer',
                'password' => 'secret',
                'is_admin' => false,
                'is_active' => true,
            ],
        );
        DB::table('legal.user_module_permissions')
            ->where('user_id', $user->getKey())
            ->delete();

        $this->actingAs($user)
            ->get(route('nsi-sgr.index'))
            ->assertForbidden();

        $this->grantGlobalModule($user, UserAccess::MODULE_NSI_SGR);

        $this->actingAs($user)
            ->get(route('nsi-sgr.index', ['q' => 'AVI CON']))
            ->assertOk()
            ->assertSee('BY.60.61.01.008.E.000040.06.26')
            ->assertSee('Эмульгатор AVI CON EMU')
            ->assertSee('Vertexco NV');
    }
}
