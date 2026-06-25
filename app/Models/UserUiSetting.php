<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUiSetting extends Model
{
    protected $table = 'legal.laravel_user_ui_settings';

    protected $primaryKey = 'user_ui_setting_id';

    protected $fillable = [
        'user_id',
        'setting_key',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
