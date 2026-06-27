<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserAccess;
use Tests\TestCase;

class BankTransactionsPaginationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->test_user());
    }

    public function test_the_application_loads_bank_transactions_page_fragment(): void
    {
        $this->getJson(route('bank-transactions.index', ['page' => 2]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonStructure(['html', 'next_page', 'has_more']);
    }

    private function test_user(): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'transactions@example.com'],
            [
                'name' => 'Transactions User',
                'password' => 'secret',
                'is_admin' => false,
                'is_active' => true,
            ],
        );

        $this->grantGlobalModule($user, UserAccess::MODULE_BANK_TRANSACTIONS);

        return $user;
    }
}
