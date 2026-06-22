<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAccessScope extends Model
{
    protected $table = 'legal.user_access_scopes';

    protected $primaryKey = 'user_access_scope_id';

    protected $fillable = [
        'user_id',
        'scope_type',
        'scope_id',
        'can_view',
        'can_import_bank_statements',
        'can_sync_bank_api',
        'can_manage_api_credentials',
        'can_edit_manual_operations',
        'can_manage_reference_data',
    ];

    protected function casts(): array
    {
        return [
            'can_view' => 'boolean',
            'can_import_bank_statements' => 'boolean',
            'can_sync_bank_api' => 'boolean',
            'can_manage_api_credentials' => 'boolean',
            'can_edit_manual_operations' => 'boolean',
            'can_manage_reference_data' => 'boolean',
        ];
    }
}
