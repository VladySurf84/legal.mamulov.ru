<?php

namespace App\Http\Controllers;

use App\Support\UserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(UserAccess::canViewBankAccounts($request->user()), 403);

        $accounts = UserAccess::bankAccountsQuery($request)
            ->select('legal.bank_account.*')
            ->selectSub(function ($query) {
                $query->from('legal.bank_account_balance_snapshots as bs')
                    ->selectRaw("json_build_object('amount', bs.amount, 'balance_on', bs.balance_on, 'source', bs.source)")
                    ->whereColumn('bs.bank_account_id', 'legal.bank_account.bank_account_id')
                    ->orderByDesc('bs.balance_on')
                    ->orderByDesc('bs.observed_at')
                    ->orderByDesc('bs.balance_snapshot_id')
                    ->limit(1);
            }, 'latest_balance')
            ->with(['bank', 'legalEntity'])
            ->orderBy('legal_id')
            ->orderBy('bank_id')
            ->orderBy('account_number')
            ->get();

        return view('bank-accounts.index', [
            'accounts' => $accounts,
            'canManageBankAccounts' => UserAccess::canManageBankAccounts($request->user()),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless(UserAccess::canManageBankAccounts($request->user()), 403);

        Artisan::call('legacy:import-bank-directories');

        return redirect()
            ->route('bank-accounts.index')
            ->with('status', trim(Artisan::output()) ?: 'Банковские справочники обновлены.');
    }
}
