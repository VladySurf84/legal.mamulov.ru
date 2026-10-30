<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal.bank_account_balance_snapshots', function (Blueprint $table) {
            $table->bigIncrements('balance_snapshot_id');
            $table->bigInteger('bank_account_id');
            $table->string('source');
            $table->bigInteger('source_id');
            $table->decimal('amount', 24, 2);
            $table->date('balance_on');
            $table->timestamp('observed_at');
            $table->jsonb('raw_data');
            $table->unique(['bank_account_id', 'source', 'source_id'], 'bank_balance_snapshot_source_unique');
            $table->index(['bank_account_id', 'balance_on', 'observed_at'], 'bank_balance_snapshot_latest_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal.bank_account_balance_snapshots');
    }
};
