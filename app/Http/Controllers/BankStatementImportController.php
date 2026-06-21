<?php

namespace App\Http\Controllers;

use App\Services\Bank\BankStatementImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class BankStatementImportController extends Controller
{
    public function store(Request $request, BankStatementImportService $service): RedirectResponse
    {
        $validated = $request->validate([
            'statement_file' => ['required', 'file', 'max:20480'],
            'bank_id' => ['nullable', 'string', 'max:20'],
            'rebuild_money_layer' => ['nullable', 'boolean'],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
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
                $file->getClientOriginalName(),
                $request->user()?->getAuthIdentifier(),
            );
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->to($this->redirectTarget($validated['redirect_to'] ?? null))
            ->with('status', sprintf(
                'Банковская выписка импортирована: запуск #%d, файл #%d, банк %s, счет %s, строк %d, операций %d.',
                $summary['import_run_id'],
                $summary['uploaded_file_id'],
                $summary['bank_id'],
                $summary['account_number'],
                $summary['rows'],
                $summary['operations'],
            ));
    }

    private function redirectTarget(?string $target): string
    {
        if ($target !== null && $target !== '') {
            $appUrl = url('/');

            if (str_starts_with($target, $appUrl) || str_starts_with($target, '/')) {
                return $target;
            }
        }

        return route('bank-transactions.index');
    }
}
