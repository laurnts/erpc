<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
final class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'account_owner_id' => User::factory(),
            'team_id' => Team::factory(),
        ];
    }

    public function configure(): Factory
    {
        // Use minutes instead of seconds to ensure distinct timestamps
        // and avoid flaky sorting tests in fast CI environments
        return $this->sequence(fn (Sequence $sequence): array => [
            'created_at' => now()->subMinutes($sequence->index),
            'updated_at' => now()->subMinutes($sequence->index),
        ]);
    }

    /**
     * Indicate that the company is a buyer.
     */
    public function buyer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_buyer' => true,
        ]);
    }

    /**
     * Indicate that the company is a supplier.
     */
    public function supplier(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_supplier' => true,
        ]);
    }

    /**
     * Indicate that the company is both a buyer and a supplier.
     */
    public function buyerAndSupplier(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_buyer' => true,
            'is_supplier' => true,
        ]);
    }

    /**
     * Indicate that the company is on hold.
     */
    public function onHold(?string $reason = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_on_hold' => true,
            'on_hold_reason' => $reason ?? $this->faker->sentence(),
        ]);
    }
}
