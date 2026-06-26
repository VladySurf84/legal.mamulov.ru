<?php

namespace App\Http\Controllers;

use App\Services\Layers\CashLayerBuilder;
use App\Support\UserAccess;
use App\Support\UserUiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class KassaController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): View|JsonResponse
    {
        abort_unless(UserAccess::canViewCashPage($request->user()), 403);

        $filters = $request->validate([
            'article_id' => ['nullable', 'integer', 'exists:legal.kassa_article,article_id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $page = (int) ($filters['page'] ?? 1);
        $filters['per_page'] ??= UserUiSettings::paginationRows($request, 'kassa-rows', self::PER_PAGE);
        $perPage = $this->perPage($filters);
        unset($filters['page']);
        unset($filters['per_page']);

        $query = DB::table('legal.cash_entries as entry')
            ->leftJoin('legal.kassa_article as article', 'article.article_id', '=', 'entry.article_id')
            ->leftJoin('legal.legal_own as legal', 'legal.legal_id', '=', 'entry.legal_id')
            ->leftJoin('legal.documents as document', 'document.document_id', '=', 'entry.source_document_id');

        if (! empty($filters['article_id'])) {
            $query->where('entry.article_id', (int) $filters['article_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('entry.occurred_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('entry.occurred_at', '<=', $filters['date_to']);
        }

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function ($query) use ($like): void {
                $query
                    ->whereRaw('entry.description ILIKE ?', [$like])
                    ->orWhereRaw('entry.source_label ILIKE ?', [$like])
                    ->orWhereRaw('article.article ILIKE ?', [$like])
                    ->orWhereRaw('legal.legal_name ILIKE ?', [$like])
                    ->orWhereRaw('legal.legal_inn ILIKE ?', [$like]);
            });
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as operations_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN entry.amount > 0 THEN entry.amount ELSE 0 END), 0) as income_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN entry.amount < 0 THEN -entry.amount ELSE 0 END), 0) as expense_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN entry.amount > 0 THEN entry.amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN entry.amount < 0 THEN -entry.amount ELSE 0 END), 0) as saldo_amount')
            ->first();

        $operationsQuery = $query
            ->select([
                'entry.cash_entry_id',
                'entry.source_type',
                'entry.source_label',
                'entry.source_document_bank_transaction_id',
                'entry.cash_operation_rule_id',
                'entry.kassa_id',
                'entry.article_id',
                'entry.occurred_at as time',
                'entry.amount',
                'entry.description',
                'entry.created_at as created',
                'entry.legal_id',
                'entry.source_document_id as document_id',
                'article.article',
                'legal.legal_name',
                'legal.legal_inn',
                'document.external_id as document_external_id',
                'document.title as document_title',
            ])
            ->selectRaw('CASE WHEN entry.amount > 0 THEN entry.amount ELSE 0 END AS entry_income_amount')
            ->selectRaw('CASE WHEN entry.amount < 0 THEN -entry.amount ELSE 0 END AS entry_expense_amount')
            ->selectRaw('SUM(entry.amount) OVER (ORDER BY entry.occurred_at, entry.cash_entry_id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS running_total')
            ->orderByDesc('entry.occurred_at')
            ->orderByDesc('entry.cash_entry_id');

        if ($perPage > 0) {
            $operationsQuery
                ->offset(($page - 1) * $perPage)
                ->limit($perPage);
        }

        $operations = $operationsQuery->get();

        $hasMore = $perPage > 0 && $page * $perPage < (int) $summary->operations_count;

        if ($request->ajax()) {
            return response()->json([
                'html' => view('kassa.partials.rows', [
                    'operations' => $operations,
                    'displayTimezone' => config('app.display_timezone', 'Europe/Moscow'),
                ])->render(),
                'loader_html' => view('kassa.partials.loader-row', [
                    'nextPage' => $hasMore ? $page + 1 : null,
                    'tableColspan' => 10,
                ])->render(),
                'sticky_summary_html' => view('kassa.partials.foot', [
                    'summary' => $summary,
                ])->render(),
                'has_more' => $hasMore,
                'next_page' => $hasMore ? $page + 1 : null,
            ]);
        }

        return view('kassa.index', [
            'operations' => $operations,
            'summary' => $summary,
            'filters' => $filters,
            'articles' => $this->articles(),
            'nextPage' => $hasMore ? $page + 1 : null,
            'canEditManualOperations' => UserAccess::canEditManualOperations($request->user()),
        ]);
    }

    public function store(Request $request, CashLayerBuilder $cashLayerBuilder): RedirectResponse
    {
        abort_unless(UserAccess::canEditManualOperations($request->user()), 403);

        $validated = $request->validate([
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
                'article_id' => (int) $validated['article_id'],
                'time' => $time,
                'amount' => $amount,
                'description' => $validated['description'],
                'created' => now('UTC')->format('Y-m-d H:i:s'),
            ]);

            $cashLayerBuilder->rebuild();
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

    public function rebuild(CashLayerBuilder $cashLayerBuilder): RedirectResponse
    {
        abort_unless(UserAccess::canEditManualOperations(request()->user()), 403);

        try {
            $count = $cashLayerBuilder->rebuild();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Не удалось пересчитать слой кассы: ' . $exception->getMessage());
        }

        return back()->with('status', "Слой кассы пересчитан: {$count} записей.");
    }

    private function articles()
    {
        return DB::table('legal.kassa_article')
            ->orderBy('article')
            ->get(['article_id', 'article']);
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

        return max(0, min(1000, $perPage));
    }
}
