<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('legal.user_module_permissions')
            ->whereIn('module', ['bank_accounts', 'bank_transactions', 'scheduler', 'user_access'])
            ->delete();

        DB::statement('ALTER TABLE legal.user_module_permissions DROP CONSTRAINT IF EXISTS user_module_permissions_module_check');
        DB::statement(<<<'SQL'
ALTER TABLE legal.user_module_permissions
ADD CONSTRAINT user_module_permissions_module_check
CHECK (module IN ('electronic_signatures', 'kassa', 'users'))
SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE legal.user_module_permissions DROP CONSTRAINT IF EXISTS user_module_permissions_module_check');
        DB::statement(<<<'SQL'
ALTER TABLE legal.user_module_permissions
ADD CONSTRAINT user_module_permissions_module_check
CHECK (module IN ('bank_accounts', 'bank_transactions', 'kassa', 'scheduler'))
SQL);
    }
};
