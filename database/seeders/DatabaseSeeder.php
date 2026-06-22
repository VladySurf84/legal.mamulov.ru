<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's reference dictionaries.
     */
    public function run(): void
    {
        $this->call([
            DocumentPartyRoleSeeder::class,
            DocumentTypeSeeder::class,
            CurrencyReferenceSeeder::class,
        ]);
    }
}
