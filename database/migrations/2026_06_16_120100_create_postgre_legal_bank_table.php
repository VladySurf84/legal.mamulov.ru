<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS "legal"."bank" (
    "bank_id" char(9) NOT NULL,
    "bank_name" varchar(500) NOT NULL,
    "api_provider_id" bigint,
    CONSTRAINT "bank_pkey" PRIMARY KEY (bank_id),
    CONSTRAINT "bank_api_provider_id_key" UNIQUE (api_provider_id)
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."bank" CASCADE
SQL);
    }
};
