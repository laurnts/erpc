<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RequestPriority;
use App\Enums\RequestStage;
use App\Models\Company;
use App\Models\Project;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Request>
 */
final class RequestFactory extends Factory
{
    protected $model = Request::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = date('Y');

        return [
            'request_number' => 'REQ-'.$year.'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'stage' => RequestStage::DRAFT,
            'priority' => $this->faker->randomElement(RequestPriority::cases()),
            'requested_at' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'required_by' => $this->faker->optional()->dateTimeBetween('now', '+60 days'),
            'internal_notes' => $this->faker->optional()->sentence(),
            'is_active' => true,
            'team_id' => Team::factory(),
            'creator_id' => User::factory(),
            'buyer_id' => Company::factory()->buyer(),
            'project_id' => null,
        ];
    }

    /**
     * Indicate that the request is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the request has a specific stage.
     */
    public function withStage(RequestStage $stage): static
    {
        return $this->state(fn (array $attributes): array => [
            'stage' => $stage,
        ]);
    }

    /**
     * Indicate that the request has a specific priority.
     */
    public function withPriority(RequestPriority $priority): static
    {
        return $this->state(fn (array $attributes): array => [
            'priority' => $priority,
        ]);
    }

    /**
     * Indicate that the request is urgent.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'priority' => RequestPriority::URGENT,
        ]);
    }

    /**
     * Indicate that the request is for a specific buyer.
     */
    public function forBuyer(?Company $buyer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_id' => $buyer ?? Company::factory()->buyer(),
        ]);
    }

    /**
     * Indicate that the request is for a specific project.
     */
    public function forProject(?Project $project = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'project_id' => $project ?? Project::factory(),
        ]);
    }

    /**
     * Indicate that the request has a required_by date.
     */
    public function withRequiredBy(\DateTimeInterface|string|null $date = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'required_by' => $date ?? $this->faker->dateTimeBetween('now', '+30 days'),
        ]);
    }

    /**
     * Indicate that the request is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stage' => RequestStage::CANCELLED,
        ]);
    }

    /**
     * Indicate that the request is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stage' => RequestStage::COMPLETED,
        ]);
    }
}
