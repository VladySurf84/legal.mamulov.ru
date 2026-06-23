<?php

namespace App\Http\Controllers;

use App\Services\Vat\VatBookImportService;
use Illuminate\Http\JsonResponse;
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

    public function entries(Request $request): View|JsonResponse
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'book_type' => ['nullable', 'in:purchase,sales'],
            'legal_id' => ['nullable', 'string', 'max:12'],
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $page = (int) ($filters['page'] ?? 1);
        $perPage = 100;
        $offset = ($page - 1) * $perPage;

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

        $searchText = trim((string) ($filters['q'] ?? ''));

        if ($searchText !== '') {
            $search = '%' . $searchText . '%';

            $baseQuery->where(function ($query) use ($search): void {
                $query
                    ->whereRaw('e.contractor_inn ILIKE ?', [$search])
                    ->orWhereRaw('e.contractor_name ILIKE ?', [$search])
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
            ->limit($perPage + 1)
            ->offset($offset)
            ->get();

        $hasMoreEntries = $entries->count() > $perPage;
        if ($hasMoreEntries) {
            $entries = $entries->slice(0, $perPage)->values();
        }

        $nextPage = $hasMoreEntries ? $page + 1 : null;

        $years = DB::table('legal.vat_book_imports')
            ->where('is_active', true)
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        $legals = DB::table('legal.legal_own')
            ->orderBy('legal_name')
            ->get(['legal_id', 'legal_name', 'legal_inn']);

        $bookLabels = [
            'purchase' => 'Покупки',
            'sales' => 'Продажи',
        ];

        if ($request->ajax()) {
            return response()->json([
                'html' => view('vat-books.partials.entry-rows', [
                    'entries' => $entries,
                    'bookLabels' => $bookLabels,
                ])->render(),
                'loader_html' => view('vat-books.partials.entries-loader-row', [
                    'nextPage' => $nextPage,
                ])->render(),
                'next_page' => $nextPage,
                'has_more' => $hasMoreEntries,
            ]);
        }

        return view('vat-books.entries', [
            'entries' => $entries,
            'filters' => $filters,
            'summary' => $summary,
            'years' => $years,
            'legals' => $legals,
            'bookLabels' => $bookLabels,
            'nextPage' => $nextPage,
        ]);
    }

    public function store(Request $request, VatBookImportService $service): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'book_files' => ['nullable', 'array', 'min:1'],
            'book_files.*' => ['required', 'file', 'max:20480'],
            'book_file' => ['nullable', 'file', 'max:20480'],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $files = array_values(array_filter($request->file('book_files', [])));
        $singleFile = $request->file('book_file');

        if ($singleFile !== null) {
            $files[] = $singleFile;
        }

        if ($files === []) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Не удалось прочитать загруженные XML-файлы.',
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', 'Не удалось прочитать загруженные XML-файлы.')
                ->with('open_modal', 'vat-book-import-dialog');
        }

        $summaries = [];
        $currentFileName = null;

        try {
            foreach ($files as $file) {
                $currentFileName = $file->getClientOriginalName();

                if ($file->getRealPath() === false) {
                    $message = sprintf('Не удалось прочитать файл %s.', $currentFileName);

                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => $message,
                        ], 422);
                    }

                    return back()
                        ->withInput()
                        ->with('error', $message)
                        ->with('open_modal', 'vat-book-import-dialog');
                }

                $summaries[] = $service->importFile($file);
            }
        } catch (Throwable $exception) {
            $message = trim(sprintf(
                '%s%s',
                $currentFileName !== null ? sprintf('Файл %s: ', $currentFileName) : '',
                $exception->getMessage(),
            ));

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', $message)
                ->with('open_modal', 'vat-book-import-dialog');
        }

        $message = sprintf(
            'Книги НДС импортированы: файлов %d, строк %d. НДС-событий %d, связей %d.',
            count($summaries),
            array_sum(array_column($summaries, 'entries_count')),
            array_sum(array_column($summaries, 'vat_events_count')),
            array_sum(array_map(
                fn (array $summary): int => (int) ($summary['accountant_report_link_stats']['inserted'] ?? 0),
                $summaries,
            )),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'files' => count($summaries),
                'entries' => array_sum(array_column($summaries, 'entries_count')),
                'vat_events' => array_sum(array_column($summaries, 'vat_events_count')),
            ]);
        }

        return redirect()
            ->to($this->redirectTarget($validated['redirect_to'] ?? null))
            ->with('status', $message)
            ->with('open_modal', 'vat-book-import-dialog');
    }

    private function redirectTarget(?string $target): string
    {
        if ($target !== null && $target !== '') {
            $appUrl = url('/');

            if (str_starts_with($target, $appUrl) || str_starts_with($target, '/')) {
                return $target;
            }
        }

        return route('vat-books.index');
    }
}
