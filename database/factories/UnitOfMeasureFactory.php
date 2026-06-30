<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
final class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->word(),
            'label' => ucfirst($this->faker->word()),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'team_id' => Team::factory(),
            'creator_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the unit is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a "pcs" (Pieces) unit.
     */
    public function pcs(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'pcs',
            'label' => 'Pieces',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * Create a "kg" (Kilograms) unit.
     */
    public function kg(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'kg',
            'label' => 'Kilograms',
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    /**
     * Create a "mt" (Metric Tons) unit.
     */
    public function mt(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'mt',
            'label' => 'Metric Tons',
            'is_active' => true,
            'sort_order' => 3,
        ]);
    }

    /**
     * Create a "set" (Sets) unit.
     */
    public function asSet(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'set',
            'label' => 'Sets',
            'is_active' => true,
            'sort_order' => 4,
        ]);
    }

    /**
     * Create a "box" (Boxes) unit.
     */
    public function box(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'box',
            'label' => 'Boxes',
            'is_active' => true,
            'sort_order' => 5,
        ]);
    }

    /**
     * Create a "roll" (Rolls) unit.
     */
    public function roll(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'roll',
            'label' => 'Rolls',
            'is_active' => true,
            'sort_order' => 6,
        ]);
    }

    /**
     * Create a "pair" (Pairs) unit.
     */
    public function pair(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'pair',
            'label' => 'Pairs',
            'is_active' => true,
            'sort_order' => 7,
        ]);
    }

    /**
     * Create a "l" (Liters) unit.
     */
    public function liters(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'l',
            'label' => 'Liters',
            'is_active' => true,
            'sort_order' => 8,
        ]);
    }

    /**
     * Create a "m" (Meters) unit.
     */
    public function meters(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'm',
            'label' => 'Meters',
            'is_active' => true,
            'sort_order' => 9,
        ]);
    }
}
