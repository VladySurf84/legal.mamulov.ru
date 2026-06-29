<?php

namespace App\Http\Controllers;

use App\Support\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NsiSgrController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(UserAccess::canViewNsiSgr($request->user()), 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'in:yes,no'],
        ]);

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

        $records = $query
            ->orderByDesc('document_date')
            ->orderByDesc('update_date_time')
            ->orderByDesc('nsi_sgr_record_id')
            ->paginate(100)
            ->withQueryString();

        $summary = DB::table('legal.nsi_sgr_records')
            ->selectRaw('count(*) as total_count')
            ->selectRaw('count(*) filter (where detail_payload is not null) as detailed_count')
            ->selectRaw("count(*) filter (where status_name = 'подписан и действует') as active_count")
            ->first();

        $state = DB::table('legal.nsi_sgr_import_state')
            ->where('state_key', 'list')
            ->first();

        return view('nsi-sgr.index', [
            'records' => $records,
            'filters' => $filters,
            'summary' => $summary,
            'state' => $state,
            'statuses' => $this->statuses(),
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
}
