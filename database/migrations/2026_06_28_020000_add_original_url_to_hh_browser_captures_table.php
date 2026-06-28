<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE legal.hh_browser_captures ADD COLUMN IF NOT EXISTS original_url text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE legal.hh_browser_captures DROP COLUMN IF EXISTS original_url');
    }
};
