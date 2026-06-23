<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramSentMessage extends Model
{
    protected $table = 'legal.telegram_sent_messages';

    protected $primaryKey = 'telegram_sent_message_id';

    protected $fillable = [
        'user_id',
        'telegram_chat_id',
        'message',
        'parse_mode',
        'disable_web_page_preview',
        'http_code',
        'response_body',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'disable_web_page_preview' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
