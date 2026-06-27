<?php

namespace App\Http\Controllers;

use App\Services\Bank\TinkoffBankSyncService;
use App\Services\Layers\CashLayerBuilder;
use App\Support\UserAccess;
use App\Support\UserUiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class BankTransactionController extends Controller
{
    private const PER_PAGE = 50;
    private const BANK_ID_TINKOFF = '044525974';

    public function index(Request $request): View|JsonResponse
    {
        abort_unless(UserAccess::canViewBankTransactions($request->user()), 403);

        $filters = $request->validate([
            'account_number' => ['nullable', 'string', 'max:20'],
            'account_numbers' => ['nullable', 'array'],
            'account_numbers.*' => ['nullable', 'string', 'max:20'],
            'type' => ['nullable', 'in:income,expense'],
            'contractor' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $filters['account_numbers'] = collect($filters['account_numbers'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $page = (int) ($filters['page'] ?? 1);
        $filters['per_page'] ??= UserUiSettings::paginationRows($request, 'bank-transactions-rows', self::PER_PAGE);
        $perPage = $this->perPage($filters);
        unset($filters['page']);
        unset($filters['per_page']);

        [$where, $bindings] = $this->whereClause($filters, $request);
        $summary = $this->summary($where, $bindings);
        $transactions = $this->transactions($where, $bindings, $page, $perPage);
        $hasMore = $perPage > 0 && $page * $perPage < $summary['count'];
        $selectedAccountNumbers = ! empty($filters['account_numbers'])
            ? $filters['account_numbers']
            : array_filter([(string) ($filters['account_number'] ?? '')]);
        $showAccountColumn = count($selectedAccountNumbers) !== 1;
        $tableColspan = $showAccountColumn ? 8 : 7;

        if ($request->ajax()) {
            return response()->json([
                'head_html' => view('bank-transactions.partials.head', [
                    'showAccountColumn' => $showAccountColumn,
                ])->render(),
                'html' => view('bank-transactions.partials.body', [
                    'transactions' => $transactions,
                    'showAccountColumn' => $showAccountColumn,
                    'tableColspan' => $tableColspan,
                ])->render(),
                'loader_html' => view('bank-transactions.partials.loader-row', [
                    'nextPage' => $hasMore ? $page + 1 : null,
                    'tableColspan' => $tableColspan,
                ])->render(),
                'sticky_summary_html' => view('bank-transactions.partials.foot', [
                    'summary' => $summary,
                    'showAccountColumn' => $showAccountColumn,
                ])->render(),
                'summary' => $summary,
                'next_page' => $hasMore ? $page + 1 : null,
                'has_more' => $hasMore,
            ]);
        }

        $accountsQuery = UserAccess::bankAccountsQuery($request)
            ->with(['bank', 'legalEntity'])
            ->orderBy('legal_id')
            ->orderBy('bank_id')
            ->orderBy('account_number');

        $accounts = $accountsQuery->get();
        $apiBankAccountIds = DB::table('legal.api_credentials as c')
            ->join('legal.bank_account as ba', DB::raw('ba.bank_account_id::text'), '=', 'c.owner_id')
            ->where('c.provider', 'tinkoff')
            ->where('c.credential_type', 'bank_api_token')
            ->where('c.owner_type', 'bank_account')
            ->where('c.status', 'active')
            ->where('ba.bank_id', self::BANK_ID_TINKOFF)
            ->pluck('ba.bank_account_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return view('bank-transactions.index', [
            'accounts' => $accounts,
            'apiAccounts' => $accounts
                ->whereIn('bank_account_id', $apiBankAccountIds)
                ->values(),
            'filters' => $filters,
            'transactions' => $transactions,
            'summary' => $summary,
            'nextPage' => $hasMore ? $page + 1 : null,
            'showAccountColumn' => $showAccountColumn,
            'tableColspan' => $tableColspan,
            'canImportBankStatements' => UserAccess::canImportBankStatements($request->user()),
            'canSyncBankApi' => UserAccess::canSyncBankApi($request->user()),
        ]);
    }

    public function sync(Request $request, TinkoffBankSyncService $service, CashLayerBuilder $cashLayerBuilder): RedirectResponse
    {
        abort_unless(UserAccess::canSyncBankApi($request->user()), 403);

        $validated = $request->validate([
            'account_number' => ['nullable', 'string', 'max:20'],
            'days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'full' => ['nullable', 'boolean'],
        ]);

        $accountNumber = trim((string) ($validated['account_number'] ?? ''));
        $days = (int) ($validated['days'] ?? config('bank.tinkoff.sync_days', 5));
        $full = (bool) ($validated['full'] ?? false);

        try {
            if ($full) {
                @set_time_limit(0);
            }

            $summary = $full
                ? $service->syncSinceActivationDates($accountNumber !== '' ? $accountNumber : null)
                : $service->sync($days, $accountNumber !== '' ? $accountNumber : null);

            $cashLayerBuilder->rebuild();
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return back()->with('status', sprintf(
            'Синхронизация Тинькофф завершена: запуск #%d, счетов %d, операций %d, период %s..%s.',
            $summary['sync_run_id'],
            $summary['accounts'],
            $summary['operations'],
            $summary['from'],
            $summary['till'],
        ));
    }

    /**
     * @param array<string, mixed> $bindings
     * @return array<int, object>
     */
    private function transactions(string $where, array $bindings, int $page, int $perPage): array
    {
        if ($perPage === 0) {
            return [];
        }

        $paginationSql = '';
        $queryBindings = $bindings;

        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $queryBindings += [
                'limit' => $perPage,
                'offset' => $offset,
            ];
            $paginationSql = 'LIMIT :limit OFFSET :offset';
        }

        return DB::select(<<<SQL
WITH pre AS (
    SELECT
        dbt.document_bank_transaction_id AS reconciliation_id,
        dbt.bank_id,
        NULL::int AS dohras,
        dbt.account_number,
        ba.name AS bank_account_name,
        to_char(dbt.operation_date, 'DD Mon YY') AS date_format,
        CASE
            WHEN btrim(dbt.account_number) = btrim(COALESCE(dbt.recipient_account, '')) THEN COALESCE(dbt.payer_name, dbt.recipient_name)
            WHEN btrim(dbt.account_number) = btrim(COALESCE(dbt.payer_account, '')) THEN COALESCE(dbt.recipient_name, dbt.payer_name)
            WHEN dbt.signed_amount >= 0 THEN COALESCE(dbt.payer_name, dbt.recipient_name)
            ELSE COALESCE(dbt.recipient_name, dbt.payer_name)
        END AS name,
        CASE
            WHEN btrim(dbt.account_number) = btrim(COALESCE(dbt.recipient_account, '')) THEN dbt.payer_inn
            WHEN btrim(dbt.account_number) = btrim(COALESCE(dbt.payer_account, '')) THEN dbt.recipient_inn
            WHEN dbt.signed_amount >= 0 THEN dbt.payer_inn
            ELSE dbt.recipient_inn
        END AS contractor_inn,
        CASE
            WHEN btrim(dbt.account_number) = btrim(COALESCE(dbt.recipient_account, '')) THEN dbt.payer_account
            WHEN btrim(dbt.account_number) = btrim(COALESCE(dbt.payer_account, '')) THEN dbt.recipient_account
            WHEN dbt.signed_amount >= 0 THEN dbt.payer_account
            ELSE dbt.recipient_account
        END AS contractor_bank_account,
        CASE WHEN dbt.signed_amount < 0 THEN -dbt.signed_amount ELSE NULL END AS amount_p,
        CASE WHEN dbt.signed_amount >= 0 THEN dbt.signed_amount ELSE NULL END AS amount_m,
        dbt.payment_purpose,
        dbt.order_intraday,
        dbt.operation_date AS date,
        dbt.signed_amount AS amount,
        dbt.operation_type AS type_alias,
        bot.name_ru AS operation_type_name,
        bot.description AS operation_type_description,
        ba.legal_id,
        l.legal_name,
        l.legal_color,
        NULL::numeric AS saldo,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM legal.vat_events ve
                WHERE ve.source_system = 'bank_payment_vat'
                    AND ve.source_document_bank_transaction_id = dbt.document_bank_transaction_id
            ) THEN 1
            ELSE 0
        END AS has_vat,
        NULL::int AS inner_ip,
        dbt.document_bank_transaction_id AS bank_transaction_id,
        NULL::bigint AS k_id,
        SUM(dbt.signed_amount) OVER(
            ORDER BY
                dbt.operation_date,
                dbt.order_intraday,
                dbt.document_bank_transaction_id
        ) AS total
    FROM legal.document_bank_transaction dbt
    LEFT JOIN legal.bank_account ba
        ON ba.bank_account_id = dbt.bank_account_id
    LEFT JOIN legal.legal_own l
        ON l.legal_id = ba.legal_id
    LEFT JOIN legal.bank_operation_types bot
        ON bot.operation_type_code = dbt.operation_type
        AND bot.is_active
    WHERE {$where}
),
main AS (
    SELECT pre.* FROM pre
)
SELECT * FROM main
ORDER BY
    date DESC,
    order_intraday DESC,
    bank_transaction_id
{$paginationSql}
SQL, $queryBindings);
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

    /**
     * @param array<string, mixed> $bindings
     * @return array{count: int, income: float, expense: float}
     */
    private function summary(string $where, array $bindings): array
    {
        $summary = DB::selectOne(<<<SQL
SELECT
    COUNT(*) AS count,
    COALESCE(SUM(CASE WHEN dbt.signed_amount >= 0 THEN dbt.signed_amount ELSE 0 END), 0) AS income,
    COALESCE(SUM(CASE WHEN dbt.signed_amount < 0 THEN -dbt.signed_amount ELSE 0 END), 0) AS expense
FROM legal.document_bank_transaction dbt
LEFT JOIN legal.bank_account ba
    ON ba.bank_account_id = dbt.bank_account_id
WHERE {$where}
SQL, $bindings);

        return [
            'count' => (int) $summary->count,
            'income' => (float) $summary->income,
            'expense' => (float) $summary->expense,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function whereClause(array $filters, Request $request): array
    {
        $where = ['true'];
        $bindings = [];

        if (! UserAccess::canViewAllGraph($request->user())) {
            $bankAccountIds = UserAccess::viewableBankAccountIds($request->user());

            if ($bankAccountIds === []) {
                $where[] = 'false';
            } else {
                $placeholders = [];

                foreach ($bankAccountIds as $index => $bankAccountId) {
                    $binding = 'access_bank_account_id_'.$index;
                    $placeholders[] = ':'.$binding;
                    $bindings[$binding] = $bankAccountId;
                }

                $where[] = 'dbt.bank_account_id IN ('.implode(', ', $placeholders).')';
            }
        }

        if (! empty($filters['account_numbers'])) {
            $placeholders = [];

            foreach ($filters['account_numbers'] as $index => $accountNumber) {
                $binding = 'account_number_'.$index;
                $placeholders[] = ':'.$binding;
                $bindings[$binding] = $accountNumber;
            }

            $where[] = 'dbt.account_number IN ('.implode(', ', $placeholders).')';
        } elseif (! empty($filters['account_number'])) {
            $where[] = 'dbt.account_number = :account_number';
            $bindings['account_number'] = $filters['account_number'];
        }

        if (($filters['type'] ?? null) === 'income') {
            $where[] = 'dbt.signed_amount >= 0';
        }

        if (($filters['type'] ?? null) === 'expense') {
            $where[] = 'dbt.signed_amount < 0';
        }

        if (! empty($filters['contractor'])) {
            $where[] = '(
                dbt.payer_name ILIKE :contractor
                OR dbt.recipient_name ILIKE :contractor
                OR dbt.payer_inn ILIKE :contractor
                OR dbt.recipient_inn ILIKE :contractor
            )';
            $bindings['contractor'] = '%'.$filters['contractor'].'%';
        }

        if (! empty($filters['date_from'])) {
            $where[] = 'dbt.operation_date >= :date_from';
            $bindings['date_from'] = $filters['date_from'];
        }

        if (! empty($filters['date_to'])) {
            $where[] = 'dbt.operation_date <= :date_to';
            $bindings['date_to'] = $filters['date_to'];
        }

        return [implode(' AND ', $where), $bindings];
    }
}
