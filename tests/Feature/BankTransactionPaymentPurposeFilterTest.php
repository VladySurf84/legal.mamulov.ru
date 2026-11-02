<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BankTransactionPaymentPurposeFilterTest extends TestCase
{
    public function test_payment_purpose_filters_rows_and_summary(): void
    {
        $this->actingAs(new User(['id' => 1, 'is_admin' => true, 'is_active' => true]));
        $expected = DB::table('legal.document_bank_transaction')
            ->whereRaw('payment_purpose ILIKE ?', ['%комиссия%'])
            ->selectRaw('count(*) as count, coalesce(sum(case when signed_amount >= 0 then signed_amount else 0 end), 0) as income, coalesce(sum(case when signed_amount < 0 then -signed_amount else 0 end), 0) as expense')
            ->first();

        $response = $this->get(route('bank-transactions.index', ['payment_purpose' => '  КОМИССИЯ  ', 'per_page' => 10]))->assertOk();
        $response->assertViewHas('summary', fn ($summary) => $summary['count'] === (int) $expected->count
            && $summary['income'] === (float) $expected->income
            && $summary['expense'] === (float) $expected->expense);
        $response->assertViewHas('transactions', function ($transactions) {
            foreach ($transactions as $transaction) {
                $this->assertStringContainsString('комиссия', mb_strtolower($transaction->payment_purpose));
            }
            return true;
        });
        $response->assertSee('name="payment_purpose"', false);
    }
}
