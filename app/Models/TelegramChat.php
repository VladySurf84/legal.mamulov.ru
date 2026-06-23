<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramChat extends Model
{
    protected $table = 'legal.telegram_chats';

    protected $primaryKey = 'telegram_chat_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'telegram_chat_id',
        'telegram_user_id',
        'type',
        'username',
        'first_name',
        'last_name',
        'title',
        'last_update_id',
        'last_message_text',
        'last_seen_at',
        'is_active',
        'raw_chat',
        'raw_from',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'raw_chat' => 'array',
            'raw_from' => 'array',
        ];
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TelegramUpdate::class, 'telegram_chat_id', 'telegram_chat_id');
    }
}
