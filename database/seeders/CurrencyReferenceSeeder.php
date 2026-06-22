<?php

namespace Database\Seeders;

use App\Support\ReferenceData\CurrencyReferenceData;
use Illuminate\Database\Seeder;

class CurrencyReferenceSeeder extends Seeder
{
    public function run(): void
    {
        CurrencyReferenceData::seed();
    }
}
