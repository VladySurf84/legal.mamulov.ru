<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal.users', function (Blueprint $table): void {
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar')->nullable();
            $table->string('role')->default('admin')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE legal.users ALTER COLUMN password DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("UPDATE legal.users SET password = '' WHERE password IS NULL");
            DB::statement('ALTER TABLE legal.users ALTER COLUMN password SET NOT NULL');
        }

        Schema::table('legal.users', function (Blueprint $table): void {
            $table->dropColumn([
                'google_id',
                'avatar',
                'role',
                'is_active',
                'last_login_at',
            ]);
        });
    }
};
