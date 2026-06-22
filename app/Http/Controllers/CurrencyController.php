<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    public function index(): View
    {
        $currencies = DB::select(<<<'SQL'
WITH raw_source_rows AS (
    SELECT btrim(currency::text) AS raw_currency, 'bank_accounts' AS source_name
    FROM legal.bank_account
    WHERE currency IS NOT NULL AND btrim(currency::text) <> ''

    UNION ALL

    SELECT btrim(currency::text) AS raw_currency, 'documents' AS source_name
    FROM legal.documents
    WHERE currency IS NOT NULL AND btrim(currency::text) <> ''

    UNION ALL

    SELECT btrim(currency::text) AS raw_currency, 'bank_transactions' AS source_name
    FROM legal.document_bank_transaction
    WHERE currency IS NOT NULL AND btrim(currency::text) <> ''

    UNION ALL

    SELECT btrim(currency::text) AS raw_currency, 'money_edges' AS source_name
    FROM legal.money_edges
    WHERE currency IS NOT NULL AND btrim(currency::text) <> ''

    UNION ALL

    SELECT btrim(currency_code::text) AS raw_currency, 'vat_book_entries' AS source_name
    FROM legal.vat_book_entries
    WHERE currency_code IS NOT NULL AND btrim(currency_code::text) <> ''
),
source_rows AS (
    SELECT
        aliases.currency_code,
        rows.source_name
    FROM raw_source_rows rows
    JOIN legal.currency_aliases aliases
        ON upper(rows.raw_currency) = aliases.currency_alias
),
aggregated AS (
    SELECT
        currency_code,
        count(*) AS usage_count,
        count(*) FILTER (WHERE source_name = 'bank_accounts') AS bank_accounts_count,
        count(*) FILTER (WHERE source_name = 'documents') AS documents_count,
        count(*) FILTER (WHERE source_name = 'bank_transactions') AS bank_transactions_count,
        count(*) FILTER (WHERE source_name = 'money_edges') AS money_edges_count,
        count(*) FILTER (WHERE source_name = 'vat_book_entries') AS vat_book_entries_count
    FROM source_rows
    GROUP BY currency_code
)
SELECT
    c.currency_code,
    c.alpha_code,
    c.name_ru,
    c.name_en,
    c.minor_units,
    c.countries,
    c.included_on,
    COALESCE(a.usage_count, 0) AS usage_count,
    COALESCE(a.bank_accounts_count, 0) AS bank_accounts_count,
    COALESCE(a.documents_count, 0) AS documents_count,
    COALESCE(a.bank_transactions_count, 0) AS bank_transactions_count,
    COALESCE(a.money_edges_count, 0) AS money_edges_count,
    COALESCE(a.vat_book_entries_count, 0) AS vat_book_entries_count
FROM legal.currencies c
LEFT JOIN aggregated a
    ON a.currency_code = c.currency_code
ORDER BY c.currency_code
SQL);

        return view('currencies.index', [
            'currencies' => $currencies,
        ]);
    }
}
