<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalEntity extends Model
{
    protected $table = 'legal.legal';

    protected $primaryKey = 'legal_id';

    public $timestamps = false;

    protected $fillable = [
        'legal_name',
        'legal_fullname',
        'legal_letter',
        'firstname',
        'lastname',
        'middlename',
        'legal_color',
        'tax_system',
        'tax_rate',
        'vat_rate',
        'legal_inn',
        'legal_ogrn',
        'legal_comment',
        'tax_periods',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
            'tax_periods' => 'array',
        ];
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'legal_id', 'legal_id');
    }
}
