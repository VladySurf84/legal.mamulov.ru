<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    protected $table = 'legal.bank';

    protected $primaryKey = 'bank_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'bank_id',
        'bank_name',
        'api_provider_id',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'bank_id', 'bank_id');
    }
}
