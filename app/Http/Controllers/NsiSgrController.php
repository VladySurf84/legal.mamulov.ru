<?php

namespace App\Http\Controllers;

use App\Support\UserAccess;
use App\Support\UserUiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NsiSgrController extends Controller
{
    private const PER_PAGE = 100;

    public function index(Request $request): View|JsonResponse
    {
        abort_unless(UserAccess::canViewNsiSgr($request->user()), 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'max:255'],
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
                    ->orWhere('manufacturer_name', 'ilike', $like)
                    ->orWhere('recipient_name', 'ilike', $like)
                    ->orWhere('recipient_inn', 'ilike', $like);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status_name', $filters['status']);
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
