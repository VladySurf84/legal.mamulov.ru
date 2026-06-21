<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Services\Bank\TinkoffBankSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class BankTransactionController extends Controller
{
    private const PER_PAGE = 100;
    private const BANK_ID_TINKOFF = '044525974';

    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->validate([
            'account_number' => ['nullable', 'string', 'max:20'],
            'type' => ['nullable', 'in:income,expense'],
            'contractor' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = (int) ($filters['page'] ?? 1);
        unset($filters['page']);

        [$where, $bindings] = $this->whereClause($filters);
        $summary = $this->summary($where, $bindings);
        $transactions = $this->transactions($where, $bindings, $page);
        $hasMore = $page * self::PER_PAGE < $summary['count'];

        if ($request->ajax()) {
            return response()->json([
                'html' => view('bank-transactions.partials.rows', [
                    'transactions' => $transactions,
                ])->render(),
                'next_page' => $hasMore ? $page + 1 : null,
                'has_more' => $hasMore,
            ]);
        }

        $accounts = BankAccount::query()
            ->with(['bank', 'legalEntity'])
            ->orderBy('legal_id')
            ->orderBy('bank_id')
            ->orderBy('account_number')
            ->get();
        $apiBankAccountIds = DB::table('legal.api_credentials as c')
            ->join('legal.bank_account as ba', 'ba.bank_account_id', '=', 'c.owner_id')
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
        ]);
    }

    public function sync(Request $request, TinkoffBankSyncService $service): RedirectResponse
    {
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
    private function transactions(string $where, array $bindings, int $page): array
    {
        $offset = ($page - 1) * self::PER_PAGE;
        $queryBindings = $bindings + [
            'limit' => self::PER_PAGE,
            'offset' => $offset,
        ];

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
            WHEN dbt.signed_amount < 0 THEN COALESCE(dbt.recipient_name, dbt.payer_name)
            ELSE COALESCE(dbt.payer_name, dbt.recipient_name)
        END AS name,
        CASE
            WHEN dbt.signed_amount < 0 THEN dbt.recipient_inn
            ELSE dbt.payer_inn
        END AS contractor_inn,
        CASE
            WHEN dbt.signed_amount < 0 THEN dbt.recipient_account
            ELSE dbt.payer_account
        END AS contractor_bank_account,
        CASE WHEN dbt.signed_amount < 0 THEN -dbt.signed_amount ELSE NULL END AS amount_p,
        CASE WHEN dbt.signed_amount >= 0 THEN dbt.signed_amount ELSE NULL END AS amount_m,
        dbt.payment_purpose,
        dbt.order_intraday,
        dbt.operation_date AS date,
        dbt.signed_amount AS amount,
        dbt.operation_type AS type_alias,
        ba.legal_id,
        l.legal_name,
        l.legal_color,
        NULL::numeric AS saldo,
        CASE
            WHEN dbt.payment_purpose ILIKE '%НДС%' OR dbt.payment_purpose ILIKE '%VAT%' THEN 1
            ELSE 0
        END AS has_vat,
        NULL::int AS inner_ip,
        dbt.document_bank_transaction_id AS bank_transaction_id,
        NULL::bigint AS k_id,
        -SUM(dbt.signed_amount) OVER(ORDER BY dbt.operation_date, dbt.signed_amount > 0, dbt.order_intraday) AS total
    FROM legal.document_bank_transaction dbt
    LEFT JOIN legal.bank_account ba
        ON ba.bank_account_id = dbt.bank_account_id
    LEFT JOIN legal.legal l
        ON l.legal_id = ba.legal_id
    WHERE {$where}
),
main AS (
    SELECT pre.* FROM pre
)
SELECT * FROM main
ORDER BY date DESC, amount < 0, order_intraday DESC
LIMIT :limit OFFSET :offset
SQL, $queryBindings);
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
    private function whereClause(array $filters): array
    {
        $where = ['true'];
        $bindings = [];

        if (! empty($filters['account_number'])) {
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
