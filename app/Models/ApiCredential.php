<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ApiCredential extends Model
{
    protected $table = 'legal.api_credentials';

    protected $primaryKey = 'api_credential_id';

    protected $fillable = [
        'provider',
        'credential_type',
        'owner_type',
        'owner_id',
        'name',
        'encrypted_secret',
        'meta',
        'status',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function secret(): string
    {
        return Crypt::decryptString($this->encrypted_secret);
    }

    /**
     * @return array<string, mixed>
     */
    public function secretPayload(): array
    {
        $secret = $this->secret();
        $payload = json_decode($secret, true);

        if (is_array($payload)) {
            return $payload;
        }

        return ['secret' => $secret];
    }

    public static function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }
}
