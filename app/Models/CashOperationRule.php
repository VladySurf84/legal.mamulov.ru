<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashOperationRule extends Model
{
    protected $table = 'legal.cash_operation_rules';

    protected $primaryKey = 'cash_operation_rule_id';

    protected $fillable = [
        'name',
        'is_active',
        'legal_id',
        'contractor_inn',
        'article_id',
        'direction',
        'valid_from',
        'valid_to',
        'priority',
        'description_template',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'priority' => 'integer',
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

    public function cashEntries(): HasMany
    {
        return $this->hasMany(CashEntry::class, 'cash_operation_rule_id', 'cash_operation_rule_id');
    }
}
