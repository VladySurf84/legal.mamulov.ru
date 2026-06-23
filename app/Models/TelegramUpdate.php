<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramUpdate extends Model
{
    protected $table = 'legal.telegram_updates';

    protected $primaryKey = 'telegram_update_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'telegram_update_id',
        'telegram_chat_id',
        'message_text',
        'update_type',
        'payload',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class, 'telegram_chat_id', 'telegram_chat_id');
    }
}
