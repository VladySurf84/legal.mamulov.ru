<?php

namespace App\Support;

class ExchangeRateBanks
{
    public const LABELS = [
        'mbank' => 'MBank',
        'obank' => 'Obank',
        'kompanion' => 'Компаньон',
        'bakai' => 'Bakai',
        'capitalbank' => 'Capital Bank',
        'baitushum' => 'Бай-Тушум',
    ];

    public const SETTINGS = [
        'accepts_rubles' => 'Прием рублей',
        'euro_acquiring' => 'Эквайринг евро',
        'visa_direct' => 'Visa direct',
    ];
}
