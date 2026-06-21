<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentBankTransaction extends Model
{
    protected $table = 'legal.document_bank_transaction';

    protected $primaryKey = 'document_bank_transaction_id';

    protected $fillable = [
        'document_id',
        'bank_account_id',
        'bank_id',
        'account_number',
        'external_operation_id',
        'external_id',
        'operation_date',
        'draw_date',
        'charge_date',
        'order_intraday',
        'amount',
        'signed_amount',
        'currency',
        'payer_name',
        'payer_inn',
        'payer_kpp',
        'payer_account',
        'payer_bic',
        'payer_bank',
        'payer_corr_account',
        'recipient_name',
        'recipient_inn',
        'recipient_kpp',
        'recipient_account',
        'recipient_bic',
        'recipient_bank',
        'recipient_corr_account',
        'payment_purpose',
        'payment_type',
        'operation_type',
        'uin',
        'creator_status',
        'kbk',
        'oktmo',
        'tax_evidence',
        'tax_period',
        'tax_doc_number',
        'tax_doc_date',
        'tax_type',
        'execution_order',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'operation_date' => 'date',
            'draw_date' => 'date',
            'charge_date' => 'date',
            'amount' => 'decimal:2',
            'signed_amount' => 'decimal:2',
            'raw_payload' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id', 'document_id');
    }
}
