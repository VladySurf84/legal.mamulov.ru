<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BankTransactionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'account_number' => ['nullable', 'string', 'max:20'],
            'type' => ['nullable', 'in:income,expense'],
            'contractor' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        [$where, $bindings] = $this->whereClause($filters);

        $transactions = DB::select(<<<SQL
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
    ORDER BY r.date, r.amount > 0, bt.order_intraday
),
main AS (
    SELECT pre.* FROM pre
)
SELECT * FROM main
SQL, $bindings);

        $summary = [
            'count' => count($transactions),
            'income' => array_sum(array_map(fn (object $row): float => (float) ($row->amount_m ?? 0), $transactions)),
            'expense' => array_sum(array_map(fn (object $row): float => (float) ($row->amount_p ?? 0), $transactions)),
        ];

        return view('bank-transactions.index', [
            'accounts' => BankAccount::query()
                ->with(['bank', 'legalEntity'])
                ->orderBy('legal_id')
                ->orderBy('bank_id')
                ->orderBy('account_number')
                ->get(),
            'filters' => $filters,
            'transactions' => $transactions,
            'summary' => $summary,
        ]);
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
