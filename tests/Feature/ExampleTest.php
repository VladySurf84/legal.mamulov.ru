<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserAccess;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->test_user());
    }

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

    private function test_user(): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'feature@example.com'],
            [
                'name' => 'Feature User',
                'password' => 'secret',
                'is_admin' => false,
                'is_active' => true,
            ],
        );

        $this->grantGlobalModule($user, UserAccess::MODULE_BANK_ACCOUNTS);
        $this->grantGlobalModule($user, UserAccess::MODULE_BANK_TRANSACTIONS);
        $this->grantGlobalModule($user, UserAccess::MODULE_DOCUMENT_TYPES);

        return $user;
    }
}
