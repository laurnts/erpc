<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CreditLimitRequestStatus;
use App\Models\BuyerCreditLimitRequest;
use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerCreditLimitRequest>
 */
final class BuyerCreditLimitRequestFactory extends Factory
{
    protected $model = BuyerCreditLimitRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currentLimit = (string) $this->faker->randomFloat(2, 1000, 10000);
        $requestedLimit = (string) ((float) $currentLimit + $this->faker->randomFloat(2, 500, 5000));

        return [
            'team_id' => Team::factory(),
            'buyer_id' => Company::factory()->buyer(),
            'current_limit' => $currentLimit,
            'requested_limit' => $requestedLimit,
            'status' => CreditLimitRequestStatus::PENDING,
            'requested_by_id' => User::factory(),
            'rejected_by_id' => null,
            'rejected_at' => null,
            'rejected_reason' => null,
        ];
    }

    /**
     * Indicate that the request is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CreditLimitRequestStatus::APPROVED,
        ]);
    }

    /**
     * Indicate that the request is rejected.
     */
    public function rejected(?User $rejectedBy = null, ?string $reason = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CreditLimitRequestStatus::REJECTED,
            'rejected_by_id' => $rejectedBy?->id ?? User::factory(),
            'rejected_at' => now(),
            'rejected_reason' => $reason ?? 'Insufficient justification',
        ]);
    }

    /**
     * Indicate that the request is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CreditLimitRequestStatus::PENDING,
        ]);
    }
}
