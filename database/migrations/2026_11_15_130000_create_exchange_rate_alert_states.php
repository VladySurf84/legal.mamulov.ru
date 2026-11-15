<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Delivery state for a notification derived from canonical exchange rates.
        Schema::create('legal.exchange_rate_alert_states', function (Blueprint $table) {
            $table->string('alert_key', 80)->primary();
            $table->decimal('last_buy_rate', 18, 8)->nullable();
            $table->boolean('pending')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        DB::table('legal.exchange_rate_alert_states')->insert([
            'alert_key' => 'kompanion_non_cash_rub',
            'last_buy_rate' => DB::table('legal.exchange_rates')
                ->where('provider', 'kompanion')->where('rate_type', 'non_cash')
                ->where('currency_code', 'RUB')->where('rate_currency_code', 'KGS')
                ->whereNull('valid_to')->value('buy_rate'),
            'pending' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('legal.exchange_rate_alert_states');
    }
};
