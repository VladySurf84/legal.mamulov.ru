<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function grantGlobalModule(User $user, string $module): void
    {
        DB::table('legal.user_module_permissions')->updateOrInsert(
            [
                'user_id' => $user->getKey(),
                'module' => $module,
                'scope_type' => 'global',
                'scope_id' => null,
            ],
            [
                'can_view' => true,
                'can_edit' => false,
                'can_manage' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
