<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TaxCode;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxCode>
 */
final class TaxCodeFactory extends Factory
{
    protected $model = TaxCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('TAX-???')),
            'name' => implode(' ', (array) $this->faker->words(2)).' Tax',
            'rate' => $this->faker->randomFloat(2, 0, 25),
            'is_inclusive_default' => false,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'team_id' => Team::factory(),
            'creator_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the tax code is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the tax code is the default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that the tax code uses inclusive tax by default.
     */
    public function inclusive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_inclusive_default' => true,
        ]);
    }

    /**
     * Create a standard PPN 11% tax code.
     */
    public function ppn11(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'PPN11',
            'name' => 'PPN 11%',
            'rate' => 11.00,
            'is_inclusive_default' => false,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * Create a PPN 0% tax code.
     */
    public function ppn0(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'PPN0',
            'name' => 'PPN 0%',
            'rate' => 0.00,
            'is_inclusive_default' => false,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 2,
        ]);
    }

    /**
     * Create a Tax Exempt tax code.
     */
    public function taxExempt(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'EXEMPT',
            'name' => 'Tax Exempt',
            'rate' => 0.00,
            'is_inclusive_default' => false,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 3,
        ]);
    }

    /**
     * Create a No Tax tax code.
     */
    public function noTax(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'NOTAX',
            'name' => 'No Tax',
            'rate' => 0.00,
            'is_inclusive_default' => false,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 4,
        ]);
    }
}
