<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manually maintained bank reference data, independent of imported quotes.
        Schema::create('legal.exchange_rate_bank_settings', function (Blueprint $table) {
            $table->string('provider', 50)->primary();
            $table->boolean('accepts_rubles')->default(false);
            $table->boolean('euro_acquiring')->default(false);
            $table->boolean('visa_direct')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal.exchange_rate_bank_settings');
    }
};
