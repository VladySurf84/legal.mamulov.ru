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
