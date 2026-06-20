<?php

namespace App\Http\Controllers;

use App\Services\Vat\VatBookImportService;
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
            ->join('legal.legal as l', 'l.legal_id', '=', 'i.legal_id')
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
                'Импортирована %s: %d Q%d, строк %d.',
                $summary['book_type'] === 'purchase' ? 'книга покупок' : 'книга продаж',
                $summary['year'],
                $summary['quarter'],
                $summary['entries_count'],
            ));
    }
}
