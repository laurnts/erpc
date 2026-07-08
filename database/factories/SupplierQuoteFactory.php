<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupplierQuoteStatus;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierQuote>
 */
final class SupplierQuoteFactory extends Factory
{
    protected $model = SupplierQuote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = date('Y');
        $quotedAt = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'quote_number' => 'SQ-'.$year.'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'supplier_reference' => $this->faker->optional()->bothify('SUP-REF-????-####'),
            'status' => SupplierQuoteStatus::PENDING,
            'exchange_rate' => '1.00000000',
            'subtotal' => '0.0000',
            'tax_total' => '0.0000',
            'total' => '0.0000',
            'subtotal_base' => '0.0000',
            'tax_total_base' => '0.0000',
            'total_base' => '0.0000',
            'quoted_at' => $quotedAt,
            'valid_until' => $this->faker->dateTimeBetween($quotedAt, '+60 days'),
            'notes' => $this->faker->optional()->sentence(),
            'internal_notes' => $this->faker->optional()->sentence(),
            'team_id' => Team::factory(),
            'creator_id' => User::factory(),
            'request_id' => Request::factory(),
            'supplier_id' => Company::factory()->supplier(),
            'currency_id' => Currency::factory(),
        ];
    }

    /**
     * Indicate that the quote is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SupplierQuoteStatus::PENDING,
        ]);
    }

    /**
     * Indicate that the quote is selected.
     */
    public function selected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SupplierQuoteStatus::SELECTED,
        ]);
    }

    /**
     * Indicate that the quote is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SupplierQuoteStatus::REJECTED,
        ]);
    }

    /**
     * Indicate that the quote is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SupplierQuoteStatus::EXPIRED,
            'valid_until' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Indicate a specific status.
     */
    public function withStatus(SupplierQuoteStatus $status): static
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
            'subtotal_base' => (string) $subtotal,
            'tax_total_base' => (string) $taxTotal,
            'total_base' => (string) $total,
        ]);
    }

    /**
     * Set exchange rate with base currency calculation.
     */
    public function withExchangeRate(float $rate): static
    {
        return $this->state(function (array $attributes) use ($rate): array {
            $subtotal = (float) ($attributes['subtotal'] ?? 0);
            $taxTotal = (float) ($attributes['tax_total'] ?? 0);
            $total = (float) ($attributes['total'] ?? 0);

            return [
                'exchange_rate' => (string) $rate,
                'subtotal_base' => (string) ($subtotal * $rate),
                'tax_total_base' => (string) ($taxTotal * $rate),
                'total_base' => (string) ($total * $rate),
            ];
        });
    }

    /**
     * Associate with a specific request.
     */
    public function forRequest(?Request $request = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'request_id' => $request ?? Request::factory(),
        ]);
    }

    /**
     * Associate with a specific supplier.
     */
    public function forSupplier(?Company $supplier = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'supplier_id' => $supplier ?? Company::factory()->supplier(),
        ]);
    }

    /**
     * Associate with a specific currency.
     */
    public function withCurrency(?Currency $currency = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'currency_id' => $currency ?? Currency::factory(),
        ]);
    }

    /**
     * Set validity period.
     */
    public function validFor(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'quoted_at' => now(),
            'valid_until' => now()->addDays($days),
        ]);
    }

    /**
     * Already expired quote.
     */
    public function alreadyExpired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'quoted_at' => now()->subDays(60),
            'valid_until' => now()->subDays(30),
            'status' => SupplierQuoteStatus::EXPIRED,
        ]);
    }

    /**
     * The request has actually been sent to the supplier (portal visibility gate).
     */
    public function sentToSupplier(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sent_to_supplier_at' => now(),
        ]);
    }

    /**
     * The supplier has declined to quote (status stays PENDING).
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes): array => [
            'declined_at' => now(),
        ]);
    }

    /**
     * Outcomes for the quote's round have been announced (round locked;
     * supplier-facing Won / "Not selected" rendering live).
     */
    public function outcomesAnnounced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'outcomes_announced_at' => now(),
        ]);
    }
}
