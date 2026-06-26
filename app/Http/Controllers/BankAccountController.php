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
        $accounts = UserAccess::bankAccountsQuery($request)
            ->with(['bank', 'legalEntity'])
            ->orderBy('legal_id')
            ->orderBy('bank_id')
            ->orderBy('account_number')
            ->get();

        return view('bank-accounts.index', [
            'accounts' => $accounts,
        ]);
    }

    public function import(): RedirectResponse
    {
        Artisan::call('legacy:import-bank-directories');

        return redirect()
            ->route('bank-accounts.index')
            ->with('status', trim(Artisan::output()) ?: 'Банковские справочники обновлены.');
    }
}
