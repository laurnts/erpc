<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
final class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'creator_id' => User::factory(),
            'from_currency_id' => Currency::factory(),
            'to_currency_id' => Currency::factory(),
            'rate' => $this->faker->randomFloat(10, 0.0001, 20000),
            'effective_date' => $this->faker->date(),
        ];
    }

    /**
     * Set the effective date to today.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes): array => [
            'effective_date' => now()->toDateString(),
        ]);
    }

    /**
     * Create an exchange rate for a specific date.
     */
    public function forDate(\DateTimeInterface|string $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'effective_date' => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date,
        ]);
    }

    /**
     * Create a USD to IDR exchange rate.
     */
    public function usdToIdr(float $rate = 15500.0): static
    {
        return $this->state(fn (array $attributes): array => [
            'rate' => $rate,
        ]);
    }
}
