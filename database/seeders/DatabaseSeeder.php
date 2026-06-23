<?php

declare(strict_types=1);

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seeders = [
            ErpPermissionSeeder::class,
            CurrencySeeder::class,
            TaxCodeSeeder::class,
            UnitOfMeasureSeeder::class,
        ];

        if (app()->isLocal()) {
            $seeders[] = LocalSeeder::class;
        }

        $this->call($seeders);
    }
}
