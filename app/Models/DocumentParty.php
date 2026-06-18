<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentParty extends Model
{
    protected $table = 'legal.document_parties';

    protected $primaryKey = 'document_party_id';

    protected $fillable = [
        'document_id',
        'party_id',
        'role',
        'role_index',
        'name_snapshot',
        'inn_snapshot',
        'kpp_snapshot',
        'country_code',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
