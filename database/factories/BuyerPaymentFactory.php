<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\BuyerInvoice;
use App\Models\BuyerPayment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerPayment>
 */
final class BuyerPaymentFactory extends Factory
{
    protected $model = BuyerPayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = date('Y');

        return [
            // High base: the real allocator starts at 1 for a fresh team, so a
            // low-range fake number risks colliding with an allocator-issued one
            // in a test that mixes factory-built and real-created documents.
            'payment_number' => 'PAY-'.$year.'-'.str_pad((string) $this->faker->unique()->numberBetween(500000, 599999), 4, '0', STR_PAD_LEFT),
            'payment_method' => $this->faker->randomElement(PaymentMethod::cases()),
            'amount' => (string) $this->faker->randomFloat(4, 100, 10000),
            'payment_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'reference_number' => $this->faker->optional()->bothify('REF-????-####'),
            'notes' => $this->faker->optional()->sentence(),
            'team_id' => Team::factory(),
            'creator_id' => User::factory(),
            'buyer_invoice_id' => BuyerInvoice::factory(),
        ];
    }

    /**
     * Set a specific buyer invoice.
     */
    public function forBuyerInvoice(?BuyerInvoice $buyerInvoice = null): static
    {
        return $this->state(function (array $attributes) use ($buyerInvoice): array {
            $invoice = $buyerInvoice ?? BuyerInvoice::factory()->create();

            return [
                'buyer_invoice_id' => $invoice->getKey(),
                'team_id' => $invoice->team_id,
            ];
        });
    }

    /**
     * Set payment method to bank transfer.
     */
    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);
    }

    /**
     * Set payment method to cash.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_method' => PaymentMethod::CASH,
        ]);
    }

    /**
     * Set payment method to check.
     */
    public function check(): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_method' => PaymentMethod::CHECK,
        ]);
    }

    /**
     * Set payment method to letter of credit.
     */
    public function letterOfCredit(): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_method' => PaymentMethod::LC,
        ]);
    }

    /**
     * Set a specific payment method.
     */
    public function withMethod(PaymentMethod $method): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_method' => $method,
        ]);
    }

    /**
     * Set a specific amount.
     */
    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes): array => [
            'amount' => (string) $amount,
        ]);
    }

    /**
     * Set a specific payment date.
     */
    public function withPaymentDate(\DateTimeInterface|string|null $date = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_date' => $date ?? now(),
        ]);
    }

    /**
     * Set a reference number.
     */
    public function withReference(string $reference): static
    {
        return $this->state(fn (array $attributes): array => [
            'reference_number' => $reference,
        ]);
    }

    /**
     * Create a full payment for an invoice.
     */
    public function fullPayment(BuyerInvoice $invoice): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_invoice_id' => $invoice->getKey(),
            'team_id' => $invoice->team_id,
            'amount' => $invoice->total,
        ]);
    }

    /**
     * Create a partial payment for an invoice.
     */
    public function partialPayment(BuyerInvoice $invoice, float $percentage = 50): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_invoice_id' => $invoice->getKey(),
            'team_id' => $invoice->team_id,
            'amount' => (string) round((float) $invoice->total * ($percentage / 100), 4),
        ]);
    }
}
