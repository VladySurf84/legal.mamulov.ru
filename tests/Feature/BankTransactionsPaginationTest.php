<?php

namespace Tests\Feature;

use Tests\TestCase;

class BankTransactionsPaginationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession(['admin_authenticated' => true]);
    }

    public function test_the_application_loads_bank_transactions_page_fragment(): void
    {
        $this->getJson(route('bank-transactions.index', ['page' => 2]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonStructure(['html', 'next_page', 'has_more']);
    }
}
