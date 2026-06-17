<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS "legal"."legal_kudir_record_type" (
    "kudir_record_type_id" integer NOT NULL,
    "kudir_record_type" varchar(200) NOT NULL,
    CONSTRAINT "legal_kudir_record_type_pkey" PRIMARY KEY (kudir_record_type_id)
)
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DROP TABLE IF EXISTS "legal"."legal_kudir_record_type" CASCADE
SQL);
    }
};
