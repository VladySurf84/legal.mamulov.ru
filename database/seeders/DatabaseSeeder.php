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
            UserSeeder::class,
            DocumentPartyRoleSeeder::class,
            DocumentTypeSeeder::class,
            CurrencyReferenceSeeder::class,
        ]);
    }
}
