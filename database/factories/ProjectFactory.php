<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Buyer;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->optional(0.7)->dateTimeBetween('-1 month', '+1 month');
        $endDate = $startDate !== null
            ? $this->faker->optional(0.8)->dateTimeBetween($startDate, '+6 months')
            : null;

        return [
            'project_number' => 'PRJ-'.date('Y').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->paragraph(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $this->faker->randomElement(ProjectStatus::cases()),
            'notes' => $this->faker->optional()->sentence(),
            'is_active' => true,
            'team_id' => Team::factory(),
            'creator_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the project is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the project has a specific status.
     */
    public function withStatus(ProjectStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    /**
     * Indicate that the project is a draft.
     */
    public function draft(): static
    {
        return $this->withStatus(ProjectStatus::DRAFT);
    }

    /**
     * Indicate that the project is active.
     */
    public function active(): static
    {
        return $this->withStatus(ProjectStatus::ACTIVE);
    }

    /**
     * Indicate that the project is completed.
     */
    public function completed(): static
    {
        return $this->withStatus(ProjectStatus::COMPLETED);
    }

    /**
     * Indicate that the project is cancelled.
     */
    public function cancelled(): static
    {
        return $this->withStatus(ProjectStatus::CANCELLED);
    }

    /**
     * Indicate that the project is on hold.
     */
    public function onHold(): static
    {
        return $this->withStatus(ProjectStatus::ON_HOLD);
    }

    /**
     * Link the project to a buyer.
     */
    public function forBuyer(?Buyer $buyer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_id' => $buyer ?? Buyer::factory(),
        ]);
    }

    /**
     * Set project dates.
     */
    public function withDates(\DateTimeInterface|string $startDate, \DateTimeInterface|string|null $endDate = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}
