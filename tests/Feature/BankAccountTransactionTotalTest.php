<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BankAccountTransactionTotalTest extends TestCase
{
    public function test_account_totals_match_transaction_income_minus_expense(): void
    {
        $accounts = BankAccount::query()->select('legal.bank_account.*')->withTransactionTotal()->get();
        $this->assertNotEmpty($accounts);

        foreach ($accounts as $account) {
            $expected = DB::table('legal.document_bank_transaction')
                ->where('bank_account_id', $account->bank_account_id)
                ->sum('signed_amount');

            $this->assertSame(number_format((float) $expected, 2, '.', ''), number_format((float) $account->transaction_total, 2, '.', ''));
        }
    }
}
