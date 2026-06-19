<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentPartyRole extends Model
{
    protected $table = 'legal.document_party_roles';

    protected $primaryKey = 'document_party_role_id';

    protected $fillable = [
        'code',
        'name',
        'description',
        'document_group',
        'is_system',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function parties(): HasMany
    {
        return $this->hasMany(DocumentParty::class, 'role', 'code');
    }
}
