<?php

namespace App\Http\Controllers;

use App\Services\Bank\BankStatementImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class BankStatementImportController extends Controller
{
    public function store(Request $request, BankStatementImportService $service): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'statement_files' => ['required', 'array', 'min:1'],
            'statement_files.*' => ['required', 'file', 'max:20480'],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $files = array_values(array_filter($request->file('statement_files', [])));

        if ($files === []) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Не удалось прочитать загруженные файлы.',
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', 'Не удалось прочитать загруженные файлы.')
                ->with('open_modal', 'bank-statement-import-dialog');
        }

        $summaries = [];
        $currentFileName = null;

        try {
            foreach ($files as $file) {
                $currentFileName = $file->getClientOriginalName();

                if ($file->getRealPath() === false) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => sprintf('Не удалось прочитать файл %s.', $currentFileName),
                        ], 422);
                    }

                    return back()
                        ->withInput()
                        ->with('error', sprintf('Не удалось прочитать файл %s.', $currentFileName))
                        ->with('open_modal', 'bank-statement-import-dialog');
                }

                $summaries[] = $service->importFile(
                    $file->getRealPath(),
                    null,
                    false,
                    $currentFileName,
                    $request->user()?->getAuthIdentifier(),
                );
            }

            app(\App\Services\Layers\MoneyLayerBuilder::class)->rebuild();
            app(\App\Services\Layers\CashLayerBuilder::class)->rebuild();
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
                ->with('open_modal', 'bank-statement-import-dialog');
        }

        $message = sprintf(
            'Банковские выписки импортированы: файлов %d, строк %d, операций %d.',
            count($summaries),
            array_sum(array_column($summaries, 'rows')),
            array_sum(array_column($summaries, 'operations')),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'files' => count($summaries),
                'rows' => array_sum(array_column($summaries, 'rows')),
                'operations' => array_sum(array_column($summaries, 'operations')),
            ]);
        }

        return redirect()
            ->to($this->redirectTarget($validated['redirect_to'] ?? null))
            ->with('status', $message)
            ->with('open_modal', 'bank-statement-import-dialog');
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
