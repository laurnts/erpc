<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BuyerQuote;
use App\Models\BuyerQuoteExtension;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerQuoteExtension>
 */
final class BuyerQuoteExtensionFactory extends Factory
{
    protected $model = BuyerQuoteExtension::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalValidUntil = $this->faker->dateTimeBetween('-10 days', '+10 days');
        $extensionDays = $this->faker->numberBetween(7, 30);

        return [
            'buyer_quote_id' => BuyerQuote::factory(),
            'extended_by_id' => User::factory(),
            'original_valid_until' => $originalValidUntil,
            'new_valid_until' => (clone $originalValidUntil)->modify("+{$extensionDays} days"),
            'reason' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Set the buyer quote this extension belongs to.
     */
    public function forBuyerQuote(?BuyerQuote $buyerQuote = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_quote_id' => $buyerQuote ?? BuyerQuote::factory(),
        ]);
    }

    /**
     * Set the user who extended the validity.
     */
    public function extendedBy(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'extended_by_id' => $user ?? User::factory(),
        ]);
    }

    /**
     * Set specific dates for the extension.
     */
    public function withDates(\DateTimeInterface $originalDate, \DateTimeInterface $newDate): static
    {
        return $this->state(fn (array $attributes): array => [
            'original_valid_until' => $originalDate,
            'new_valid_until' => $newDate,
        ]);
    }

    /**
     * Set a specific reason for the extension.
     */
    public function withReason(string $reason): static
    {
        return $this->state(fn (array $attributes): array => [
            'reason' => $reason,
        ]);
    }

    /**
     * Create an extension with no reason.
     */
    public function withoutReason(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reason' => null,
        ]);
    }

    /**
     * Extend by a specific number of days.
     */
    public function extendByDays(int $days): static
    {
        return $this->state(function (array $attributes) use ($days): array {
            $original = $attributes['original_valid_until'] ?? now();

            return [
                'new_valid_until' => (clone $original)->modify("+{$days} days"),
            ];
        });
    }
}
