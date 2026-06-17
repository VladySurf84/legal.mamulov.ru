<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $table = 'legal.document_types';

    protected $primaryKey = 'document_type_id';

    protected $fillable = [
        'code',
        'name',
        'document_group',
        'is_primary',
        'is_tax_document',
        'is_money_document',
        'is_inventory_document',
        'is_contract_document',
        'creates_accounting_events',
        'creates_management_events',
        'creates_tax_events',
        'requires_parties',
        'requires_lines',
        'supports_corrections',
        'supports_files',
        'default_direction',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_tax_document' => 'boolean',
            'is_money_document' => 'boolean',
            'is_inventory_document' => 'boolean',
            'is_contract_document' => 'boolean',
            'creates_accounting_events' => 'boolean',
            'creates_management_events' => 'boolean',
            'creates_tax_events' => 'boolean',
            'requires_parties' => 'boolean',
            'requires_lines' => 'boolean',
            'supports_corrections' => 'boolean',
            'supports_files' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
