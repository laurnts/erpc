<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
final class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => "\u{20AC}"],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => "\u{00A3}"],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => "\u{00A5}"],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => "\u{00A5}"],
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$'],
        ];

        $currency = $this->faker->randomElement($currencies);

        return [
            'code' => $this->faker->unique()->lexify('???'),
            'name' => $currency['name'],
            'symbol' => $currency['symbol'],
            'decimal_places' => 2,
            'is_active' => true,
            'is_default' => false,
        ];
    }

    /**
     * Create a specific currency.
     */
    public function usd(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
        ]);
    }

    /**
     * Create Indonesian Rupiah currency.
     */
    public function idr(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'decimal_places' => 0,
        ]);
    }

    /**
     * Create Euro currency.
     */
    public function eur(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => "\u{20AC}",
            'decimal_places' => 2,
        ]);
    }

    /**
     * Create Singapore Dollar currency.
     */
    public function sgd(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'SGD',
            'name' => 'Singapore Dollar',
            'symbol' => 'S$',
            'decimal_places' => 2,
        ]);
    }

    /**
     * Create Chinese Yuan currency.
     */
    public function cny(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'CNY',
            'name' => 'Chinese Yuan',
            'symbol' => "\u{00A5}",
            'decimal_places' => 2,
        ]);
    }

    /**
     * Indicate that the currency is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that this is the default currency.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }
}
