<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $table = 'legal.bank_account';

    protected $primaryKey = 'bank_account_id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'bank_account_id',
        'account_number',
        'bank_id',
        'legal_id',
        'name',
        'currency',
        'account_type',
        'activation_date',
        'balance_otb',
        'balance_authorized',
        'balance_pending_payments',
        'balance_pending_requisitions',
    ];

    protected function casts(): array
    {
        return [
            'activation_date' => 'date',
            'balance_otb' => 'decimal:2',
            'balance_authorized' => 'decimal:2',
            'balance_pending_payments' => 'decimal:2',
            'balance_pending_requisitions' => 'decimal:2',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id', 'bank_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_id', 'legal_id');
    }

    public function apiCredentials(): HasMany
    {
        return $this->hasMany(ApiCredential::class, 'owner_id', 'bank_account_id')
            ->where('owner_type', 'bank_account');
    }
}
