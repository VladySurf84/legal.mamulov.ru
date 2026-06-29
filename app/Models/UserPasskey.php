<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPasskey extends Model
{
    protected $table = 'legal.laravel_user_passkeys';

    protected $primaryKey = 'user_passkey_id';

    protected $fillable = [
        'user_id',
        'name',
        'credential_id',
        'credential_public_key',
        'signature_count',
        'transports',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_count' => 'integer',
            'transports' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
