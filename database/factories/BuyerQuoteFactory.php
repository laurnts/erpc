<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BuyerQuoteStatus;
use App\Models\Buyer;
use App\Models\BuyerQuote;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerQuote>
 */
final class BuyerQuoteFactory extends Factory
{
    protected $model = BuyerQuote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = date('Y');

        return [
            'quote_number' => 'BQ-'.$year.'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'version' => 1,
            'previous_version_id' => null,
            'status' => BuyerQuoteStatus::DRAFT,
            'exchange_rate' => '1.00000000',
            'subtotal' => '0.0000',
            'tax_total' => '0.0000',
            'total' => '0.0000',
            'prepayment_percent' => $this->faker->numberBetween(0, 50),
            'payment_terms_days' => $this->faker->randomElement([15, 30, 45, 60]),
            'payment_terms_description' => $this->faker->optional()->sentence(),
            'issued_at' => null,
            'valid_until' => $this->faker->dateTimeBetween('now', '+30 days'),
            'terms_and_conditions' => $this->faker->optional()->paragraphs(2, true),
            'notes' => $this->faker->optional()->sentence(),
            'internal_notes' => $this->faker->optional()->sentence(),
            'team_id' => Team::factory(),
            'creator_id' => User::factory(),
            'request_id' => Request::factory(),
            'buyer_id' => Buyer::factory(),
            'currency_id' => Currency::factory(),
        ];
    }

    /**
     * Indicate that the quote is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BuyerQuoteStatus::DRAFT,
            'issued_at' => null,
        ]);
    }

    /**
     * Indicate that the quote has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BuyerQuoteStatus::SENT,
            'issued_at' => now(),
        ]);
    }

    /**
     * Indicate that the quote has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BuyerQuoteStatus::ACCEPTED,
            'issued_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Indicate that the quote has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BuyerQuoteStatus::REJECTED,
            'issued_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Indicate that the quote has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BuyerQuoteStatus::EXPIRED,
            'issued_at' => $this->faker->dateTimeBetween('-60 days', '-30 days'),
            'valid_until' => $this->faker->dateTimeBetween('-10 days', '-1 day'),
        ]);
    }

    /**
     * Indicate that the quote has been superseded by a newer version.
     */
    public function superseded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BuyerQuoteStatus::SUPERSEDED,
        ]);
    }

    /**
     * Set a specific status.
     */
    public function withStatus(BuyerQuoteStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    /**
     * Set a specific version number.
     */
    public function withVersion(int $version): static
    {
        return $this->state(fn (array $attributes): array => [
            'version' => $version,
        ]);
    }

    /**
     * Set the previous version for versioning.
     */
    public function withPreviousVersion(?BuyerQuote $previousQuote = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'previous_version_id' => $previousQuote?->getKey(),
            'version' => ($previousQuote?->version ?? 0) + 1,
        ]);
    }

    /**
     * Set specific totals.
     */
    public function withTotals(float $subtotal, float $taxTotal, float $total): static
    {
        return $this->state(fn (array $attributes): array => [
            'subtotal' => (string) $subtotal,
            'tax_total' => (string) $taxTotal,
            'total' => (string) $total,
        ]);
    }

    /**
     * Set a specific buyer.
     */
    public function forBuyer(?Buyer $buyer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_id' => $buyer ?? Buyer::factory(),
        ]);
    }

    /**
     * Set a specific request.
     */
    public function forRequest(?Request $request = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'request_id' => $request ?? Request::factory(),
        ]);
    }

    /**
     * Set a specific currency.
     */
    public function withCurrency(?Currency $currency = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'currency_id' => $currency ?? Currency::factory(),
        ]);
    }

    /**
     * Set validity date.
     */
    public function validUntil(\DateTimeInterface|string|null $date = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'valid_until' => $date ?? $this->faker->dateTimeBetween('now', '+30 days'),
        ]);
    }

    /**
     * Create a quote that is about to expire (within 3 days).
     */
    public function aboutToExpire(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BuyerQuoteStatus::SENT,
            'issued_at' => now()->subDays(27),
            'valid_until' => now()->addDays($this->faker->numberBetween(1, 3)),
        ]);
    }
}
