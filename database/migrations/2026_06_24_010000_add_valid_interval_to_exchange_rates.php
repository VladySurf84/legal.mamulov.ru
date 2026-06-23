<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE legal.exchange_rates ADD COLUMN IF NOT EXISTS valid_from timestamp(0) without time zone');
        DB::statement('ALTER TABLE legal.exchange_rates ADD COLUMN IF NOT EXISTS valid_to timestamp(0) without time zone');

        DB::statement(<<<'SQL'
UPDATE legal.exchange_rates
SET
    valid_from = CASE
        WHEN bank_valid_from IS NOT NULL AND bank_valid_from <= observed_from THEN bank_valid_from
        ELSE observed_from
    END,
    valid_to = observed_to,
    updated_at = CURRENT_TIMESTAMP
WHERE valid_from IS DISTINCT FROM CASE
        WHEN bank_valid_from IS NOT NULL AND bank_valid_from <= observed_from THEN bank_valid_from
        ELSE observed_from
    END
   OR valid_to IS DISTINCT FROM observed_to
SQL);

        DB::statement('ALTER TABLE legal.exchange_rates ALTER COLUMN valid_from SET NOT NULL');
        DB::statement('ALTER TABLE legal.exchange_rates DROP CONSTRAINT IF EXISTS exchange_rates_valid_interval_check');
        DB::statement('ALTER TABLE legal.exchange_rates ADD CONSTRAINT exchange_rates_valid_interval_check CHECK (valid_to IS NULL OR valid_to >= valid_from)');

        DB::statement('DROP INDEX IF EXISTS legal.exchange_rates_lookup_idx');
        DB::statement('DROP INDEX IF EXISTS legal.exchange_rates_current_idx');
        DB::statement('CREATE INDEX exchange_rates_lookup_idx ON legal.exchange_rates (provider, rate_type, currency_code, valid_from, valid_to)');
        DB::statement('CREATE INDEX IF NOT EXISTS exchange_rates_observed_idx ON legal.exchange_rates (provider, rate_type, currency_code, observed_from, observed_to)');
        DB::statement('CREATE INDEX exchange_rates_current_idx ON legal.exchange_rates (provider, rate_type, currency_code) WHERE valid_to IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS legal.exchange_rates_observed_idx');
        DB::statement('DROP INDEX IF EXISTS legal.exchange_rates_current_idx');
        DB::statement('DROP INDEX IF EXISTS legal.exchange_rates_lookup_idx');
        DB::statement('ALTER TABLE legal.exchange_rates DROP CONSTRAINT IF EXISTS exchange_rates_valid_interval_check');
        DB::statement('ALTER TABLE legal.exchange_rates DROP COLUMN IF EXISTS valid_to');
        DB::statement('ALTER TABLE legal.exchange_rates DROP COLUMN IF EXISTS valid_from');
        DB::statement('CREATE INDEX IF NOT EXISTS exchange_rates_lookup_idx ON legal.exchange_rates (provider, rate_type, currency_code, observed_from, observed_to)');
        DB::statement('CREATE INDEX IF NOT EXISTS exchange_rates_current_idx ON legal.exchange_rates (provider, rate_type, currency_code) WHERE observed_to IS NULL');
    }
};
