<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class KassaController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'legal_id' => ['nullable', 'string', 'max:12'],
            'article_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = DB::table('legal.kassa as k')
            ->leftJoin('legal.kassa_article as article', 'article.article_id', '=', 'k.article_id')
            ->leftJoin('legal.legal_own as legal', 'legal.legal_id', '=', 'k.legal_id')
            ->leftJoin('legal.documents as document', 'document.document_id', '=', 'k.document_id');

        if (! empty($filters['legal_id'])) {
            $query->where('k.legal_id', (string) $filters['legal_id']);
        }

        if (! empty($filters['article_id'])) {
            $query->where('k.article_id', (int) $filters['article_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('k.time', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('k.time', '<=', $filters['date_to']);
        }

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function ($query) use ($like): void {
                $query
                    ->whereRaw('k.description ILIKE ?', [$like])
                    ->orWhereRaw('article.article ILIKE ?', [$like])
                    ->orWhereRaw('legal.legal_name ILIKE ?', [$like])
                    ->orWhereRaw('legal.legal_inn ILIKE ?', [$like]);
            });
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as operations_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN k.amount > 0 THEN k.amount ELSE 0 END), 0) as income_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN k.amount < 0 THEN -k.amount ELSE 0 END), 0) as expense_amount')
            ->selectRaw('COALESCE(SUM(k.amount), 0) as saldo_amount')
            ->first();

        $operations = $query
            ->select([
                'k.kassa_id',
                'k.article_id',
                'k.time',
                'k.amount',
                'k.description',
                'k.created',
                'k.reconciliation_id',
                'k.legal_id',
                'k.document_id',
                'article.article',
                'legal.legal_name',
                'legal.legal_inn',
                'document.external_id as document_external_id',
                'document.title as document_title',
            ])
            ->selectRaw('SUM(k.amount) OVER (ORDER BY k.time, k.kassa_id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS running_total')
            ->orderByDesc('k.time')
            ->orderByDesc('k.kassa_id')
            ->limit(500)
            ->get();

        return view('kassa.index', [
            'operations' => $operations,
            'summary' => $summary,
            'filters' => $filters,
            'legalEntities' => $this->legalEntities(),
            'articles' => $this->articles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'legal_id' => ['required', 'string', 'max:12', 'exists:legal.legal_own,legal_id'],
            'article_id' => ['required', 'integer', 'exists:legal.kassa_article,article_id'],
            'time' => ['required', 'date'],
            'direction' => ['required', 'in:income,expense'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $displayTimezone = config('app.display_timezone', 'Europe/Moscow');
        $amount = (int) $validated['amount'];

        if ($validated['direction'] === 'expense') {
            $amount = -$amount;
        }

        $time = Carbon::parse($validated['time'], $displayTimezone)
            ->timezone('UTC')
            ->format('Y-m-d H:i:s');

        try {
            DB::table('legal.kassa')->insert([
                'legal_id' => $validated['legal_id'],
                'article_id' => (int) $validated['article_id'],
                'time' => $time,
                'amount' => $amount,
                'description' => $validated['description'],
                'created' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('open_modal', 'kassa-create-dialog')
                ->with('error', 'Не удалось добавить кассовую запись: ' . $exception->getMessage());
        }

        return redirect()
            ->route('kassa.index')
            ->with('status', 'Кассовая запись добавлена.');
    }

    private function legalEntities()
    {
        return DB::table('legal.legal_own')
            ->orderBy('legal_name')
            ->get(['legal_id', 'legal_name', 'legal_inn']);
    }

    private function articles()
    {
        return DB::table('legal.kassa_article')
            ->orderBy('article')
            ->get(['article_id', 'article']);
    }
}
