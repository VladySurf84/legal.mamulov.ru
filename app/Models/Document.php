<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $table = 'legal.documents';

    protected $primaryKey = 'document_id';

    protected $fillable = [
        'document_type_id',
        'document_date',
        'document_number',
        'title',
        'amount',
        'currency',
        'status',
        'source_system',
        'external_id',
        'external_hash',
        'source_api_sync_request_id',
        'source_file_id',
        'metadata',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id', 'document_type_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(DocumentParty::class, 'document_id', 'document_id');
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(DocumentBankTransaction::class, 'document_id', 'document_id');
    }
}
