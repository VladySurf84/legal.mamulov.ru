<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashEntry extends Model
{
    protected $table = 'legal.cash_entries';

    protected $primaryKey = 'cash_entry_id';

    protected $fillable = [
        'source_type',
        'source_label',
        'source_document_id',
        'source_document_bank_transaction_id',
        'kassa_id',
        'cash_operation_rule_id',
        'legal_id',
        'article_id',
        'occurred_at',
        'amount',
        'currency',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'source_document_id' => 'integer',
            'source_document_bank_transaction_id' => 'integer',
            'kassa_id' => 'integer',
            'cash_operation_rule_id' => 'integer',
            'article_id' => 'integer',
            'occurred_at' => 'datetime',
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_id', 'legal_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KassaArticle::class, 'article_id', 'article_id');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id', 'document_id');
    }

    public function sourceBankTransaction(): BelongsTo
    {
        return $this->belongsTo(DocumentBankTransaction::class, 'source_document_bank_transaction_id', 'document_bank_transaction_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CashOperationRule::class, 'cash_operation_rule_id', 'cash_operation_rule_id');
    }
}
