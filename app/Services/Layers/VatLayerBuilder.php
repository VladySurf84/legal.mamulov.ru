<?php

namespace App\Services\Layers;

use Illuminate\Support\Facades\DB;

class VatLayerBuilder
{
    public const SOURCE_SYSTEM = 'accountant_vat_book';

    public function rebuild(): int
    {
        return DB::transaction(function (): int {
            DB::table('legal.vat_events')
                ->where('source_system', self::SOURCE_SYSTEM)
                ->delete();

            DB::insert(<<<'SQL'
INSERT INTO legal.vat_events (
    source_system,
    source_vat_book_import_id,
    source_vat_book_entry_id,
    legal_id,
    year,
    quarter,
    book_type,
    vat_direction,
    occurred_on,
    invoice_number,
    invoice_date,
    contractor_name,
    contractor_inn,
    contractor_kpp,
    amount_total,
    amount_without_vat,
    vat_amount,
    signed_vat_amount,
    operation_code,
    metadata,
    created_at,
    updated_at
)
SELECT
    ?,
    e.vat_book_import_id,
    e.vat_book_entry_id,
    e.legal_id,
    e.year,
    e.quarter,
    e.book_type,
    CASE WHEN e.book_type = 'purchase' THEN 'input' ELSE 'output' END,
    COALESCE(e.acceptance_date, e.invoice_date),
    e.invoice_number,
    e.invoice_date,
    e.contractor_name,
    e.contractor_inn,
    e.contractor_kpp,
    e.amount_total,
    e.amount_without_vat,
    ABS(COALESCE(e.vat_amount, 0)),
    CASE
        WHEN e.book_type = 'purchase' THEN -ABS(COALESCE(e.vat_amount, 0))
        ELSE ABS(COALESCE(e.vat_amount, 0))
    END,
    e.operation_code,
    jsonb_build_object(
        'source', 'vat_book_entry',
        'vat_book_import_id', e.vat_book_import_id,
        'row_number', e.row_number,
        'period_code', e.period_code,
        'payment_doc_number', e.payment_doc_number,
        'payment_doc_date', e.payment_doc_date
    ),
    now(),
    now()
FROM legal.vat_book_entries e
JOIN legal.vat_book_imports i
    ON i.vat_book_import_id = e.vat_book_import_id
WHERE i.is_active
  AND e.vat_amount IS NOT NULL
SQL, [self::SOURCE_SYSTEM]);

            return DB::table('legal.vat_events')
                ->where('source_system', self::SOURCE_SYSTEM)
                ->count();
        });
    }
}
