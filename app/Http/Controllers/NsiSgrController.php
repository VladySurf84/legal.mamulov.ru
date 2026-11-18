<?php

namespace App\Http\Controllers;

use App\Services\Nsi\NsiSgrSyncService;
use App\Support\UserAccess;
use App\Support\UserUiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class NsiSgrController extends Controller
{
    private const PER_PAGE = 100;

    public function index(Request $request): View|JsonResponse
    {
        abort_unless(UserAccess::canViewNsiSgr($request->user()), 403);

        if (is_string($request->input('status'))) {
            $request->merge(['status' => array_values(array_filter([$request->input('status')], fn ($value) => $value !== ''))]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'manufacturer' => ['nullable', 'string', 'max:200'],
            'number' => ['nullable', 'string', 'max:200'],
            'product' => ['nullable', 'string', 'max:200'],
            'recipient' => ['nullable', 'string', 'max:200'],
            'use_area' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'array', 'max:100'],
            'status.*' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'in:yes,no'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $page = (int) ($filters['page'] ?? 1);
        $filters['per_page'] ??= UserUiSettings::paginationRows($request, 'nsi-sgr-rows', self::PER_PAGE);
        $perPage = $this->perPage($filters);
        unset($filters['page'], $filters['per_page']);

        $query = DB::table('legal.nsi_sgr_records');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($query) use ($like): void {
                $query->where('sgr_number', 'ilike', $like)
                    ->orWhere('product_name', 'ilike', $like)
                    ->orWhere('recipient_name', 'ilike', $like)
                    ->orWhere('recipient_inn', 'ilike', $like);
            });
        }

        foreach ([
            'number' => ['sgr_number', 'serial_number'],
            'product' => ['product_name', 'product_code'],
            'manufacturer' => ['manufacturer_name', 'manufacturer_address'],
            'recipient' => ['recipient_name', 'recipient_inn', 'recipient_address'],
            'use_area' => ['use_area'],
        ] as $filter => $columns) {
            $value = trim((string) ($filters[$filter] ?? ''));
            if ($value === '') {
                continue;
            }

            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $value).'%';
            $query->where(function ($query) use ($columns, $like): void {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'ilike', $like);
                }
            });
        }

        if (! empty($filters['status'])) {
            $query->whereIn('status_name', $filters['status']);
        }

        if (($filters['details'] ?? null) === 'yes') {
            $query->whereNotNull('detail_payload');
        } elseif (($filters['details'] ?? null) === 'no') {
            $query->whereNull('detail_payload');
        }

        $filteredSummary = (clone $query)
            ->selectRaw('count(*) as total_count')
            ->selectRaw('count(*) filter (where detail_payload is not null) as detailed_count')
            ->selectRaw("count(*) filter (where status_name = 'подписан и действует') as active_count")
            ->first();

        $records = (clone $query)
            ->orderByDesc('document_date')
            ->orderByDesc('update_date_time')
            ->orderByDesc('nsi_sgr_record_id')
            ->limit($perPage + 1)
            ->offset(($page - 1) * $perPage)
            ->get();

        $hasMore = $records->count() > $perPage;
        if ($hasMore) {
            $records->pop();
        }

        $nextPage = $hasMore ? $page + 1 : null;

        $summary = DB::table('legal.nsi_sgr_records')
            ->selectRaw('count(*) as total_count')
            ->selectRaw('count(*) filter (where detail_payload is not null) as detailed_count')
            ->selectRaw("count(*) filter (where status_name = 'подписан и действует') as active_count")
            ->first();

        $state = DB::table('legal.nsi_sgr_import_state')
            ->where('state_key', 'list')
            ->first();

        $tableColspan = 8;

        if ($request->ajax()) {
            return response()->json([
                'html' => view('nsi-sgr.partials.rows', [
                    'records' => $records,
                    'tableColspan' => $tableColspan,
                ])->render(),
                'loader_html' => view('nsi-sgr.partials.loader-row', [
                    'nextPage' => $nextPage,
                    'tableColspan' => $tableColspan,
                ])->render(),
                'sticky_summary_html' => view('nsi-sgr.partials.foot', [
                    'filteredSummary' => $filteredSummary,
                    'state' => $state,
                ])->render(),
                'next_page' => $nextPage,
                'has_more' => $hasMore,
            ]);
        }

        return view('nsi-sgr.index', [
            'records' => $records,
            'filters' => $filters,
            'summary' => $summary,
            'filteredSummary' => $filteredSummary,
            'state' => $state,
            'statuses' => $this->statuses(),
            'nextPage' => $nextPage,
            'tableColspan' => $tableColspan,
        ]);
    }

    public function show(Request $request, int $recordId): JsonResponse|View
    {
        abort_unless(UserAccess::canViewNsiSgr($request->user()), 403);

        $record = DB::table('legal.nsi_sgr_records')
            ->where('nsi_sgr_record_id', $recordId)
            ->first();

        abort_if($record === null, 404);

        $view = view('nsi-sgr.partials.detail', [
            'record' => $record,
            'statusHistory' => DB::table('legal.nsi_sgr_status_history')
                ->where('nsi_sgr_record_id', $recordId)
                ->orderByDesc('nsi_sgr_status_history_id')
                ->get(),
        ]);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'html' => $view->render(),
                'title' => (string) $record->sgr_number,
            ]);
        }

        return $view;
    }

    public function refresh(Request $request, int $recordId, NsiSgrSyncService $syncService): RedirectResponse
    {
        abort_unless(UserAccess::canViewNsiSgr($request->user()), 403);

        $record = DB::table('legal.nsi_sgr_records')
            ->where('nsi_sgr_record_id', $recordId)
            ->first(['sgr_number']);

        abort_if($record === null, 404);

        try {
            $syncService->syncDetailRecord($recordId, [
                'started_by_type' => 'user',
                'started_by_user_id' => $request->user()?->getKey(),
                'started_from' => 'nsi_sgr_context_menu',
            ]);
        } catch (Throwable $exception) {
            return back()->with('error', 'Не удалось обновить СГР: '.$exception->getMessage());
        }

        return back()->with('status', sprintf('СГР %s обновлена через API.', $record->sgr_number));
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return DB::table('legal.nsi_sgr_records')
            ->whereNotNull('status_name')
            ->distinct()
            ->orderBy('status_name')
            ->pluck('status_name', 'status_name')
            ->map(fn ($status) => (string) $status)
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function perPage(array $filters): int
    {
        if (! array_key_exists('per_page', $filters) || $filters['per_page'] === null || $filters['per_page'] === '') {
            return self::PER_PAGE;
        }

        $perPage = (int) $filters['per_page'];

        if ($perPage <= 0) {
            return self::PER_PAGE;
        }

        return min(1000, $perPage);
    }
}
