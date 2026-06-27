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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class KassaController extends Controller
{
    private const PER_PAGE = 50;
    private const FRESH_ENTRY_DAYS = 7;

    public function index(Request $request): View|JsonResponse
    {
        abort_unless(UserAccess::canViewCashPage($request->user()), 403);

        $articleFilter = $request->input('article_id', []);
        $articleFilter = is_array($articleFilter) ? $articleFilter : [$articleFilter];
        $request->merge([
            'article_id' => array_values(array_filter($articleFilter, static fn ($value) => $value !== null && $value !== '')),
        ]);

        $filters = $request->validate([
            'article_id' => ['nullable', 'array'],
            'article_id.*' => ['integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $filters['article_id'] = collect($filters['article_id'] ?? [])
            ->map(static fn ($articleId) => (int) $articleId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $page = (int) ($filters['page'] ?? 1);
        $filters['per_page'] ??= UserUiSettings::paginationRows($request, 'kassa-rows', self::PER_PAGE);
        $perPage = $this->perPage($filters);
        unset($filters['page']);
        unset($filters['per_page']);

        $query = DB::table('legal.cash_entries as entry')
            ->leftJoin('legal.kassa as kassa', 'kassa.kassa_id', '=', 'entry.kassa_id')
            ->leftJoin('legal.kassa_article as article', 'article.article_id', '=', 'entry.article_id')
            ->leftJoin('legal.legal_own as legal', 'legal.legal_id', '=', 'entry.legal_id')
            ->leftJoin('legal.documents as document', 'document.document_id', '=', 'entry.source_document_id');

        if (! empty($filters['article_id'])) {
            $query->whereIn('entry.article_id', $filters['article_id']);
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
                'kassa.created as kassa_created',
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
                    'canCreateCashEntry' => UserAccess::canCreateCashEntry($request->user()),
                    'canEditAnyCashEntry' => UserAccess::canEditAnyCashEntry($request->user()),
                    'canDeleteFreshCashEntry' => UserAccess::canDeleteFreshCashEntry($request->user()),
                    'canDeleteAnyCashEntry' => UserAccess::canDeleteAnyCashEntry($request->user()),
                    'freshEntryDays' => self::FRESH_ENTRY_DAYS,
                ])->render(),
                'loader_html' => view('kassa.partials.loader-row', [
                    'nextPage' => $hasMore ? $page + 1 : null,
                    'tableColspan' => 8,
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
            'canCreateCashEntry' => UserAccess::canCreateCashEntry($request->user()),
            'canEditAnyCashEntry' => UserAccess::canEditAnyCashEntry($request->user()),
            'canDeleteCashEntry' => UserAccess::canDeleteCashEntry($request->user()),
            'canDeleteFreshCashEntry' => UserAccess::canDeleteFreshCashEntry($request->user()),
            'canDeleteAnyCashEntry' => UserAccess::canDeleteAnyCashEntry($request->user()),
            'freshEntryDays' => self::FRESH_ENTRY_DAYS,
            'canRebuildCashLayer' => UserAccess::canRebuildCashLayer($request->user()),
        ]);
    }

    public function store(Request $request, CashLayerBuilder $cashLayerBuilder): RedirectResponse|JsonResponse
    {
        abort_unless(UserAccess::canCreateCashEntry($request->user()), 403);

        $entryData = $this->validatedEntryData($request);
        $displayTimezone = config('app.display_timezone', 'Europe/Moscow');
        $time = now($displayTimezone)
            ->timezone('UTC')
            ->format('Y-m-d H:i:s');

        try {
            DB::table('legal.kassa')->insert([
                'article_id' => $entryData['article_id'],
                'time' => $time,
                'amount' => $entryData['amount'],
                'description' => $entryData['description'],
                'created' => now('UTC')->format('Y-m-d H:i:s'),
            ]);

            $cashLayerBuilder->rebuild();
        } catch (Throwable $exception) {
            report($exception);

            if ($request->ajax()) {
                return response()->json([
                    'message' => 'Не удалось добавить кассовую запись: ' . $exception->getMessage(),
                ], 500);
            }

            return back()
                ->withInput()
                ->with('open_modal', 'kassa-create-dialog')
                ->with('error', 'Не удалось добавить кассовую запись: ' . $exception->getMessage());
        }

        if ($request->ajax()) {
            return response()->json([
                'message' => 'Кассовая запись добавлена.',
            ]);
        }

        return redirect()
            ->route('kassa.index')
            ->with('status', 'Кассовая запись добавлена.');
    }

    public function update(Request $request, CashLayerBuilder $cashLayerBuilder, int $kassaId): RedirectResponse|JsonResponse
    {
        abort_unless(
            UserAccess::canCreateCashEntry($request->user()) || UserAccess::canEditAnyCashEntry($request->user()),
            403
        );

        $entryData = $this->validatedEntryData($request);

        try {
            $entry = DB::table('legal.kassa')
                ->where('kassa_id', $kassaId)
                ->first(['kassa_id', 'created']);

            if (! $entry) {
                throw ValidationException::withMessages([
                    'kassa_id' => 'Кассовая запись не найдена.',
                ]);
            }

            if (! UserAccess::canEditAnyCashEntry($request->user())) {
                $this->abortUnlessFreshManualEntry($entry->created);
            }

            DB::table('legal.kassa')
                ->where('kassa_id', $kassaId)
                ->update([
                    'article_id' => $entryData['article_id'],
                    'amount' => $entryData['amount'],
                    'description' => $entryData['description'],
                ]);

            $cashLayerBuilder->rebuild();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            if ($request->ajax()) {
                return response()->json([
                    'message' => 'Не удалось обновить кассовую запись: ' . $exception->getMessage(),
                ], 500);
            }

            return back()
                ->withInput()
                ->with('open_modal', 'kassa-create-dialog')
                ->with('error', 'Не удалось обновить кассовую запись: ' . $exception->getMessage());
        }

        if ($request->ajax()) {
            return response()->json([
                'message' => 'Кассовая запись обновлена.',
            ]);
        }

        return redirect()
            ->route('kassa.index')
            ->with('status', 'Кассовая запись обновлена.');
    }

    public function destroy(Request $request, CashLayerBuilder $cashLayerBuilder, int $kassaId): RedirectResponse|JsonResponse
    {
        abort_unless(UserAccess::canDeleteCashEntry($request->user()), 403);

        try {
            $entry = DB::table('legal.kassa')
                ->where('kassa_id', $kassaId)
                ->first(['kassa_id', 'created']);

            if (! $entry) {
                throw ValidationException::withMessages([
                    'kassa_id' => 'Кассовая запись не найдена.',
                ]);
            }

            if (! UserAccess::canDeleteAnyCashEntry($request->user())) {
                $this->abortUnlessFreshManualEntry($entry->created);
            }

            $deleted = DB::table('legal.kassa')
                ->where('kassa_id', $kassaId)
                ->delete();

            if ($deleted === 0) {
                throw ValidationException::withMessages([
                    'kassa_id' => 'Кассовая запись не найдена.',
                ]);
            }

            $cashLayerBuilder->rebuild();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            if ($request->ajax()) {
                return response()->json([
                    'message' => 'Не удалось удалить кассовую запись: ' . $exception->getMessage(),
                ], 500);
            }

            return back()
                ->with('error', 'Не удалось удалить кассовую запись: ' . $exception->getMessage());
        }

        if ($request->ajax()) {
            return response()->json([
                'message' => 'Кассовая запись удалена.',
            ]);
        }

        return redirect()
            ->route('kassa.index')
            ->with('status', 'Кассовая запись удалена.');
    }

    public function rebuild(CashLayerBuilder $cashLayerBuilder): RedirectResponse
    {
        abort_unless(UserAccess::canRebuildCashLayer(request()->user()), 403);

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

    private function articleExists(int $articleId): bool
    {
        return DB::table('legal.kassa_article')
            ->where('article_id', $articleId)
            ->exists();
    }

    private function abortUnlessFreshManualEntry(mixed $created): void
    {
        $createdAt = $created
            ? Carbon::parse((string) $created, 'UTC')
            : null;

        abort_unless(
            $createdAt !== null && $createdAt->greaterThanOrEqualTo(now('UTC')->subDays(self::FRESH_ENTRY_DAYS)),
            403
        );
    }

    /**
     * @return array{article_id: int|null, amount: int, description: string}
     */
    private function validatedEntryData(Request $request): array
    {
        $validated = $request->validate([
            'article_id' => ['nullable', 'integer'],
            'direction' => ['required', 'in:income,expense'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $articleId = filled($validated['article_id'] ?? null) ? (int) $validated['article_id'] : null;

        if ($articleId !== null && ! $this->articleExists($articleId)) {
            throw ValidationException::withMessages([
                'article_id' => 'Выбранное описание не найдено.',
            ]);
        }

        $amount = (int) $validated['amount'];

        if ($validated['direction'] === 'expense') {
            $amount = -$amount;
        }

        return [
            'article_id' => $articleId,
            'amount' => $amount,
            'description' => $validated['description'],
        ];
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
