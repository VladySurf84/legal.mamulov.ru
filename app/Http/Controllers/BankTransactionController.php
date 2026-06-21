<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Services\Bank\TinkoffBankSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        ]);

        $accountNumber = trim((string) ($validated['account_number'] ?? ''));
        $days = (int) ($validated['days'] ?? config('bank.tinkoff.sync_days', 5));

        try {
            $summary = $this->shouldProxySync()
                ? $this->proxySyncRequest($days, $accountNumber !== '' ? $accountNumber : null)
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

    public function proxySync(Request $request, TinkoffBankSyncService $service): JsonResponse
    {
        $expectedToken = (string) config('bank.tinkoff.sync_proxy_token');
        $actualToken = (string) $request->header('X-Tinkoff-Sync-Proxy-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $actualToken)) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        $validated = $request->validate([
            'account_number' => ['nullable', 'string', 'max:20'],
            'days' => ['nullable', 'integer', 'min:1', 'max:366'],
        ]);

        $accountNumber = trim((string) ($validated['account_number'] ?? ''));
        $days = (int) ($validated['days'] ?? config('bank.tinkoff.sync_days', 5));

        $summary = $service->sync($days, $accountNumber !== '' ? $accountNumber : null);

        return response()->json([
            'message' => 'Tinkoff sync complete',
            'summary' => $summary,
        ]);
    }

    private function shouldProxySync(): bool
    {
        return filled(config('bank.tinkoff.sync_proxy_url'));
    }

    /**
     * @return array<string, mixed>
     */
    private function proxySyncRequest(int $days, ?string $accountNumber): array
    {
        $proxyUrl = (string) config('bank.tinkoff.sync_proxy_url');
        $proxyToken = (string) config('bank.tinkoff.sync_proxy_token');

        if ($proxyToken === '') {
            throw new \RuntimeException('TINKOFF_SYNC_PROXY_TOKEN is not configured.');
        }

        $response = Http::acceptJson()
            ->timeout(300)
            ->withHeaders([
                'X-Tinkoff-Sync-Proxy-Token' => $proxyToken,
            ])
            ->post($proxyUrl, [
                'days' => $days,
                'account_number' => $accountNumber,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException($response->json('message') ?: $response->body());
        }

        $summary = $response->json('summary');

        if (! is_array($summary)) {
            throw new \RuntimeException('Tinkoff sync proxy returned an invalid response.');
        }

        return $summary;
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
        r.reconciliation_id,
        bt.bank_id,
        bt.dohras,
        bt.account_number,
        ba.name AS bank_account_name,
        to_char(r.date, 'DD Mon YY') AS date_format,
        bt.contractor_name AS name,
        r.contractor_inn,
        bt.contractor_bank_account,
        CASE WHEN r.amount < 0 THEN -r.amount ELSE NULL END AS amount_p,
        CASE WHEN r.amount >= 0 THEN r.amount ELSE NULL END AS amount_m,
        bt.payment_purpose,
        bt.order_intraday,
        r.date,
        r.amount,
        bt.type_alias,
        r.legal_id,
        l.legal_name,
        l.legal_color,
        lbs.saldo,
        bt.has_vat,
        bt.inner_ip,
        bt.bank_transaction_id,
        k.kassa_id AS k_id,
        -SUM(r.amount) OVER(ORDER BY r.date, r.amount > 0, bt.order_intraday) AS total
    FROM legal.legal_reconciliation r
    JOIN legal.bank_transaction bt USING(reconciliation_type_id, reconciliation_id)
    LEFT JOIN legal.bank_account ba
        ON ba.bank_id = bt.bank_id
        AND ba.account_number = bt.account_number
        AND ba.legal_id = r.legal_id
    LEFT JOIN legal.legal l
        ON l.legal_id = r.legal_id
    LEFT JOIN legal.legal_buh_saldo lbs
        ON lbs.legal_id = r.legal_id
        AND lbs.contractor_inn = r.contractor_inn
    LEFT JOIN legal.kassa k
        ON k.reconciliation_id = r.reconciliation_id
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
    COALESCE(SUM(CASE WHEN r.amount >= 0 THEN r.amount ELSE 0 END), 0) AS income,
    COALESCE(SUM(CASE WHEN r.amount < 0 THEN -r.amount ELSE 0 END), 0) AS expense
FROM legal.legal_reconciliation r
JOIN legal.bank_transaction bt USING(reconciliation_type_id, reconciliation_id)
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
            $where[] = 'bt.account_number = :account_number';
            $bindings['account_number'] = $filters['account_number'];
        }

        if (($filters['type'] ?? null) === 'income') {
            $where[] = 'r.amount >= 0';
        }

        if (($filters['type'] ?? null) === 'expense') {
            $where[] = 'r.amount < 0';
        }

        if (! empty($filters['contractor'])) {
            $where[] = '(bt.contractor_name ILIKE :contractor OR r.contractor_inn::text ILIKE :contractor)';
            $bindings['contractor'] = '%'.$filters['contractor'].'%';
        }

        if (! empty($filters['date_from'])) {
            $where[] = 'r.date >= :date_from';
            $bindings['date_from'] = $filters['date_from'];
        }

        if (! empty($filters['date_to'])) {
            $where[] = 'r.date <= :date_to';
            $bindings['date_to'] = $filters['date_to'];
        }

        return [implode(' AND ', $where), $bindings];
    }
}
