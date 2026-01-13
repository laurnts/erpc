<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerOrder>
 */
final class BuyerOrderFactory extends Factory
{
    protected $model = BuyerOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = date('Y');

        return [
            'order_number' => 'BO-'.$year.'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => OrderStatus::DRAFT,
            'subtotal' => '0.00',
            'tax_total' => '0.00',
            'total' => '0.00',
            'payment_terms_days' => $this->faker->randomElement([15, 30, 45, 60]),
            'payment_terms_text' => $this->faker->optional()->sentence(),
            'notes' => $this->faker->optional()->sentence(),
            'internal_notes' => $this->faker->optional()->sentence(),
            'ordered_at' => now(),
            'confirmed_at' => null,
            'team_id' => Team::factory(),
            'creator_id' => User::factory(),
            'request_id' => Request::factory(),
            'buyer_id' => Company::factory()->buyer(),
            'buyer_quote_id' => null,
        ];
    }

    /**
     * Indicate that the order is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::DRAFT,
            'confirmed_at' => null,
        ]);
    }

    /**
     * Indicate that the order is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the order is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::PROCESSING,
            'confirmed_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Indicate that the order is shipped.
     */
    public function shipped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::SHIPPED,
            'confirmed_at' => $this->faker->dateTimeBetween('-30 days', '-7 days'),
        ]);
    }

    /**
     * Indicate that the order is delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::DELIVERED,
            'confirmed_at' => $this->faker->dateTimeBetween('-30 days', '-7 days'),
        ]);
    }

    /**
     * Indicate that the order is invoiced.
     */
    public function invoiced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::INVOICED,
            'confirmed_at' => $this->faker->dateTimeBetween('-30 days', '-7 days'),
        ]);
    }

    /**
     * Indicate that the order is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::COMPLETED,
            'confirmed_at' => $this->faker->dateTimeBetween('-60 days', '-30 days'),
        ]);
    }

    /**
     * Indicate that the order is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::CANCELLED,
        ]);
    }

    /**
     * Set a specific status.
     */
    public function withStatus(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
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
    public function forBuyer(?Company $buyer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_id' => $buyer ?? Company::factory()->buyer(),
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
     * Set a source buyer quote.
     */
    public function fromQuote(?BuyerQuote $buyerQuote = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_quote_id' => $buyerQuote ?? BuyerQuote::factory(),
        ]);
    }

    /**
     * Set payment terms.
     */
    public function withPaymentTerms(int $days, ?string $text = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_terms_days' => $days,
            'payment_terms_text' => $text,
        ]);
    }
}
