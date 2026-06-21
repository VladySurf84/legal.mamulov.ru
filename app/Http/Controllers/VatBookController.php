<?php

namespace App\Http\Controllers;

use App\Services\Vat\VatBookImportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class VatBookController extends Controller
{
    public function index(): View
    {
        $imports = DB::table('legal.vat_book_imports as i')
            ->join('legal.legal_own as l', 'l.legal_id', '=', 'i.legal_id')
            ->orderByDesc('i.year')
            ->orderByDesc('i.quarter')
            ->orderBy('i.book_type')
            ->orderByDesc('i.imported_at')
            ->limit(100)
            ->get([
                'i.vat_book_import_id',
                'i.book_type',
                'i.year',
                'i.quarter',
                'i.source_file_name',
                'i.stored_path',
                'i.entries_count',
                'i.total_amount',
                'i.total_amount_without_vat',
                'i.total_vat_amount',
                'i.is_active',
                'i.imported_at',
                'l.legal_name',
                'l.legal_inn',
            ]);

        return view('vat-books.index', [
            'imports' => $imports,
        ]);
    }

    public function entries(Request $request): View
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'book_type' => ['nullable', 'in:purchase,sales'],
            'legal_id' => ['nullable', 'string', 'max:12'],
            'contractor_inn' => ['nullable', 'string', 'max:12'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $baseQuery = DB::table('legal.vat_book_entries as e')
            ->join('legal.vat_book_imports as i', 'i.vat_book_import_id', '=', 'e.vat_book_import_id')
            ->join('legal.legal_own as l', 'l.legal_id', '=', 'e.legal_id')
            ->where('i.is_active', true);

        if (!empty($filters['year'])) {
            $baseQuery->where('e.year', (int) $filters['year']);
        }

        if (!empty($filters['quarter'])) {
            $baseQuery->where('e.quarter', (int) $filters['quarter']);
        }

        if (!empty($filters['book_type'])) {
            $baseQuery->where('e.book_type', $filters['book_type']);
        }

        if (!empty($filters['legal_id'])) {
            $baseQuery->where('e.legal_id', (string) $filters['legal_id']);
        }

        if (!empty($filters['contractor_inn'])) {
            $baseQuery->where('e.contractor_inn', preg_replace('/\D+/', '', $filters['contractor_inn']));
        }

        if (!empty($filters['q'])) {
            $search = '%' . $filters['q'] . '%';

            $baseQuery->where(function ($query) use ($search): void {
                $query
                    ->whereRaw('e.contractor_name ILIKE ?', [$search])
                    ->orWhereRaw('e.invoice_number ILIKE ?', [$search])
                    ->orWhereRaw('e.payment_doc_number ILIKE ?', [$search])
                    ->orWhereRaw('e.operation_code ILIKE ?', [$search]);
            });
        }

        $summary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as entries_count')
            ->selectRaw('COALESCE(SUM(e.amount_total), 0) as amount_total')
            ->selectRaw('COALESCE(SUM(e.amount_without_vat), 0) as amount_without_vat')
            ->selectRaw('COALESCE(SUM(e.vat_amount), 0) as vat_amount')
            ->first();

        /** @var LengthAwarePaginator $entries */
        $entries = $baseQuery
            ->orderByDesc('e.year')
            ->orderByDesc('e.quarter')
            ->orderBy('e.book_type')
            ->orderBy('e.row_number')
            ->select([
                'e.vat_book_entry_id',
                'e.book_type',
                'e.year',
                'e.quarter',
                'e.row_number',
                'e.operation_code',
                'e.invoice_number',
                'e.invoice_date',
                'e.correction_invoice_number',
                'e.correction_invoice_date',
                'e.acceptance_date',
                'e.payment_doc_number',
                'e.payment_doc_date',
                'e.contractor_name',
                'e.contractor_inn',
                'e.contractor_kpp',
                'e.currency_code',
                'e.amount_total',
                'e.amount_without_vat',
                'e.vat_amount',
                'i.source_file_name',
                'l.legal_name',
                'l.legal_inn',
            ])
            ->paginate(100)
            ->withQueryString();

        $years = DB::table('legal.vat_book_imports')
            ->where('is_active', true)
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        $legals = DB::table('legal.legal_own')
            ->orderBy('legal_name')
            ->get(['legal_id', 'legal_name', 'legal_inn']);

        return view('vat-books.entries', [
            'entries' => $entries,
            'filters' => $filters,
            'summary' => $summary,
            'years' => $years,
            'legals' => $legals,
        ]);
    }

    public function store(Request $request, VatBookImportService $service): RedirectResponse
    {
        $validated = $request->validate([
            'book_file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('book_file');

        if ($file === null || $file->getRealPath() === false) {
            return back()
                ->with('error', 'Не удалось прочитать загруженный XML-файл.');
        }

        try {
            $summary = $service->importFile($file);
        } catch (Throwable $exception) {
            return back()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('vat-books.index')
            ->with('status', sprintf(
                'Импортирована %s: %d Q%d, строк %d. НДС-событий %d, связей %d.',
                $summary['book_type'] === 'purchase' ? 'книга покупок' : 'книга продаж',
                $summary['year'],
                $summary['quarter'],
                $summary['entries_count'],
                $summary['vat_events_count'],
                $summary['accountant_report_link_stats']['inserted'],
            ));
    }
}
