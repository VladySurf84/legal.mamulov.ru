<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.source_exchange_rate_quotes (
    source_exchange_rate_quote_id bigserial PRIMARY KEY,
    source_record_id bigint NOT NULL
        REFERENCES legal.source_records(source_record_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    provider varchar(50) NOT NULL,
    rate_type varchar(50) NOT NULL,
    currency_code char(3) NOT NULL,
    rate_currency_code char(3) NOT NULL DEFAULT 'KGS',
    buy_rate numeric(18,8),
    sell_rate numeric(18,8),
    official_rate numeric(18,8),
    bank_valid_from timestamp(0) without time zone,
    observed_at timestamp(0) without time zone NOT NULL,
    raw_item jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT source_exchange_rate_quotes_provider_not_empty CHECK (btrim(provider) <> ''),
    CONSTRAINT source_exchange_rate_quotes_rate_type_not_empty CHECK (btrim(rate_type) <> ''),
    CONSTRAINT source_exchange_rate_quotes_rates_present CHECK (
        buy_rate IS NOT NULL OR sell_rate IS NOT NULL OR official_rate IS NOT NULL
    ),
    CONSTRAINT source_exchange_rate_quotes_source_record_unique UNIQUE (source_record_id)
)
SQL);

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS legal.exchange_rates (
    exchange_rate_id bigserial PRIMARY KEY,
    provider varchar(50) NOT NULL,
    rate_type varchar(50) NOT NULL,
    currency_code char(3) NOT NULL,
    rate_currency_code char(3) NOT NULL DEFAULT 'KGS',
    buy_rate numeric(18,8),
    sell_rate numeric(18,8),
    official_rate numeric(18,8),
    bank_valid_from timestamp(0) without time zone,
    observed_from timestamp(0) without time zone NOT NULL,
    observed_to timestamp(0) without time zone,
    first_seen_at timestamp(0) without time zone NOT NULL,
    last_seen_at timestamp(0) without time zone NOT NULL,
    first_source_record_id bigint
        REFERENCES legal.source_records(source_record_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    last_source_record_id bigint
        REFERENCES legal.source_records(source_record_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    quote_hash char(64) NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT exchange_rates_provider_not_empty CHECK (btrim(provider) <> ''),
    CONSTRAINT exchange_rates_rate_type_not_empty CHECK (btrim(rate_type) <> ''),
    CONSTRAINT exchange_rates_interval_check CHECK (observed_to IS NULL OR observed_to >= observed_from),
    CONSTRAINT exchange_rates_rates_present CHECK (
        buy_rate IS NOT NULL OR sell_rate IS NOT NULL OR official_rate IS NOT NULL
    )
)
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS source_exchange_rate_quotes_observed_idx ON legal.source_exchange_rate_quotes (provider, rate_type, currency_code, observed_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS source_exchange_rate_quotes_source_record_idx ON legal.source_exchange_rate_quotes (source_record_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS exchange_rates_lookup_idx ON legal.exchange_rates (provider, rate_type, currency_code, observed_from, observed_to)');
        DB::statement('CREATE INDEX IF NOT EXISTS exchange_rates_current_idx ON legal.exchange_rates (provider, rate_type, currency_code) WHERE observed_to IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS exchange_rates_quote_hash_idx ON legal.exchange_rates (quote_hash)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS legal.exchange_rates');
        DB::statement('DROP TABLE IF EXISTS legal.source_exchange_rate_quotes');
    }
};
