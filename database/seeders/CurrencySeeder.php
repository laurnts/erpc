<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

final class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_active' => true,
                'is_default' => true,
            ],
            [
                'code' => 'IDR',
                'name' => 'Indonesian Rupiah',
                'symbol' => 'Rp',
                'decimal_places' => 0,
                'thousands_separator' => '.',
                'decimal_separator' => ',',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => "\u{20AC}",
                'decimal_places' => 2,
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'code' => 'SGD',
                'name' => 'Singapore Dollar',
                'symbol' => 'S$',
                'decimal_places' => 2,
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'code' => 'CNY',
                'name' => 'Chinese Yuan',
                'symbol' => "\u{00A5}",
                'decimal_places' => 2,
                'is_active' => true,
                'is_default' => false,
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
