<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_opens_document_types(): void
    {
        $this->get('/')->assertRedirect(route('bank-accounts.index'));

        $this->get(route('document-types.index'))
            ->assertOk()
            ->assertSee('Типы документов');
    }

    public function test_the_application_opens_bank_accounts(): void
    {
        $this->get(route('bank-accounts.index'))
            ->assertOk()
            ->assertSee('Банковские счета');
    }

    public function test_the_application_opens_bank_transactions(): void
    {
        $this->get(route('bank-transactions.index'))
            ->assertOk()
            ->assertSee('Банковские транзакции');
    }
}
