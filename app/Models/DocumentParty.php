<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentParty extends Model
{
    protected $table = 'legal.document_parties';

    protected $primaryKey = 'document_party_id';

    protected $fillable = [
        'document_id',
        'party_id',
        'document_party_role_id',
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

    public function roleDefinition(): BelongsTo
    {
        return $this->belongsTo(DocumentPartyRole::class, 'document_party_role_id', 'document_party_role_id');
    }
}
