<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BuyerCreditLimitRequest;
use App\Models\BuyerCreditLimitRequestApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerCreditLimitRequestApproval>
 */
final class BuyerCreditLimitRequestApprovalFactory extends Factory
{
    protected $model = BuyerCreditLimitRequestApproval::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_credit_limit_request_id' => BuyerCreditLimitRequest::factory(),
            'user_id' => User::factory(),
            'approved_at' => now(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the approval has notes.
     */
    public function withNotes(?string $notes = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'notes' => $notes ?? $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the approval has no notes.
     */
    public function withoutNotes(): static
    {
        return $this->state(fn (array $attributes): array => [
            'notes' => null,
        ]);
    }
}
