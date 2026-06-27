<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserModulePermission extends Model
{
    protected $table = 'legal.user_module_permissions';

    protected $primaryKey = 'user_module_permission_id';

    protected $fillable = [
        'user_id',
        'module',
        'scope_type',
        'scope_id',
        'can_view',
        'can_edit',
        'can_manage',
    ];

    protected function casts(): array
    {
        return [
            'can_view' => 'boolean',
            'can_edit' => 'boolean',
            'can_manage' => 'boolean',
        ];
    }
}
