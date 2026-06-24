<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KassaArticle extends Model
{
    protected $table = 'legal.kassa_article';

    protected $primaryKey = 'article_id';

    public $timestamps = false;

    protected $fillable = [
        'article',
    ];

    public function cashEntries(): HasMany
    {
        return $this->hasMany(CashEntry::class, 'article_id', 'article_id');
    }

    public function cashOperationRules(): HasMany
    {
        return $this->hasMany(CashOperationRule::class, 'article_id', 'article_id');
    }
}
