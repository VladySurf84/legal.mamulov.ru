<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    public function index(): View
    {
        $accounts = BankAccount::query()
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
