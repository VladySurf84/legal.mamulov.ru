<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS "legal"."legal_kudir_record" (
    "legal_id" bigint NOT NULL,
    "year" integer DEFAULT 0 NOT NULL,
    "date" date NOT NULL,
    "amount" numeric(10, 2) NOT NULL,
    "kudir_record_type_id" integer DEFAULT 1,
    "description" varchar(2000) NOT NULL
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_kudir_record" CASCADE
SQL);
    }
};
