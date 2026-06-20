<?php

namespace App\Http\Controllers;

use App\Services\Bank\OzonBankStatementImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class OzonBankStatementController extends Controller
{
    public function create(): View
    {
        return view('ozon-bank-statements.create');
    }

    public function store(Request $request, OzonBankStatementImportService $service): RedirectResponse
    {
        $validated = $request->validate([
            'statement_file' => ['required', 'file', 'max:20480'],
            'bank_id' => ['nullable', 'string', 'max:20'],
            'rebuild_money_layer' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('statement_file');

        if ($file === null || $file->getRealPath() === false) {
            return back()
                ->withInput()
                ->with('error', 'Не удалось прочитать загруженный файл.');
        }

        try {
            $summary = $service->importFile(
                $file->getRealPath(),
                ($validated['bank_id'] ?? '') !== '' ? $validated['bank_id'] : null,
                (bool) ($validated['rebuild_money_layer'] ?? false),
            );
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('ozon-bank-statements.create')
            ->with('status', sprintf(
                'Файл Ozon импортирован: банк %s, счет %s, строк %d, операций %d.',
                $summary['bank_id'],
                $summary['account_number'],
                $summary['rows'],
                $summary['operations'],
            ));
    }
}
