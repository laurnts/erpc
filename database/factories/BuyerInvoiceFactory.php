<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerInvoice>
 */
final class BuyerInvoiceFactory extends Factory
{
    protected $model = BuyerInvoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = date('Y');

        return [
            'invoice_number' => 'INV-'.$year.'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'type' => InvoiceType::STANDARD,
            'status' => InvoiceStatus::DRAFT,
            'exchange_rate' => '1.00000000',
            'subtotal' => '0.0000',
            'tax_total' => '0.0000',
            'total' => '0.0000',
            'amount_paid' => '0.0000',
            'net_days' => $this->faker->randomElement([15, 30, 45, 60]),
            'issued_at' => null,
            'due_at' => null,
            'notes' => $this->faker->optional()->sentence(),
            'team_id' => Team::factory(),
            'creator_id' => User::factory(),
            'request_id' => Request::factory(),
            'currency_id' => Currency::factory(),
            'buyer_order_id' => null,
            'original_invoice_id' => null,
            'credit_reason' => null,
        ];
    }

    /**
     * Indicate that the invoice is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::DRAFT,
            'issued_at' => null,
            'due_at' => null,
        ]);
    }

    /**
     * Indicate that the invoice has been sent.
     */
    public function sent(): static
    {
        return $this->state(function (array $attributes): array {
            $issuedAt = $this->faker->dateTimeBetween('-30 days', 'now');
            $dueAt = (clone $issuedAt)->modify('+'.($attributes['net_days'] ?? 30).' days');

            return [
                'status' => InvoiceStatus::SENT,
                'issued_at' => $issuedAt,
                'due_at' => $dueAt,
            ];
        });
    }

    /**
     * Indicate that the invoice is partially paid.
     */
    public function partial(): static
    {
        return $this->state(function (array $attributes): array {
            $issuedAt = $this->faker->dateTimeBetween('-30 days', '-7 days');
            $dueAt = (clone $issuedAt)->modify('+'.($attributes['net_days'] ?? 30).' days');
            $total = (float) ($attributes['total'] ?? 1000);
            $amountPaid = $total * $this->faker->randomFloat(2, 0.1, 0.9);

            return [
                'status' => InvoiceStatus::PARTIAL,
                'issued_at' => $issuedAt,
                'due_at' => $dueAt,
                'amount_paid' => (string) round($amountPaid, 4),
            ];
        });
    }

    /**
     * Indicate that the invoice is paid.
     */
    public function paid(): static
    {
        return $this->state(function (array $attributes): array {
            $issuedAt = $this->faker->dateTimeBetween('-60 days', '-14 days');
            $dueAt = (clone $issuedAt)->modify('+'.($attributes['net_days'] ?? 30).' days');
            $total = (float) ($attributes['total'] ?? 1000);

            return [
                'status' => InvoiceStatus::PAID,
                'issued_at' => $issuedAt,
                'due_at' => $dueAt,
                'amount_paid' => (string) round($total, 4),
            ];
        });
    }

    /**
     * Indicate that the invoice is overdue.
     */
    public function overdue(): static
    {
        return $this->state(function (array $attributes): array {
            $issuedAt = $this->faker->dateTimeBetween('-60 days', '-45 days');
            $dueAt = (clone $issuedAt)->modify('+'.($attributes['net_days'] ?? 30).' days');

            return [
                'status' => InvoiceStatus::OVERDUE,
                'issued_at' => $issuedAt,
                'due_at' => $dueAt,
            ];
        });
    }

    /**
     * Indicate that the invoice is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::CANCELLED,
        ]);
    }

    /**
     * Indicate that this is a prepayment invoice.
     */
    public function prepayment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => InvoiceType::PREPAYMENT,
        ]);
    }

    /**
     * Indicate that this is a balance invoice.
     */
    public function balance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => InvoiceType::BALANCE,
        ]);
    }

    /**
     * Indicate that this is a credit note.
     */
    public function creditNote(?BuyerInvoice $originalInvoice = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => InvoiceType::CREDIT_NOTE,
            'original_invoice_id' => $originalInvoice?->getKey(),
            'credit_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that this is a debit note.
     */
    public function debitNote(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => InvoiceType::DEBIT_NOTE,
        ]);
    }

    /**
     * Set a specific status.
     */
    public function withStatus(InvoiceStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    /**
     * Set a specific type.
     */
    public function withType(InvoiceType $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
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
     * Set a specific currency.
     */
    public function withCurrency(?Currency $currency = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'currency_id' => $currency ?? Currency::factory(),
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
     * Set a source buyer order.
     */
    public function forBuyerOrder(?BuyerOrder $buyerOrder = null): static
    {
        return $this->state(function (array $attributes) use ($buyerOrder): array {
            $order = $buyerOrder ?? BuyerOrder::factory()->create();

            return [
                'buyer_order_id' => $order->getKey(),
                'request_id' => $order->request_id,
                'team_id' => $order->team_id,
            ];
        });
    }

    /**
     * Set payment terms.
     */
    public function withNetDays(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'net_days' => $days,
        ]);
    }

    /**
     * Set issued and due dates.
     */
    public function withDates(\DateTimeInterface|string|null $issuedAt = null, \DateTimeInterface|string|null $dueAt = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'issued_at' => $issuedAt ?? now(),
            'due_at' => $dueAt ?? now()->addDays($attributes['net_days'] ?? 30),
        ]);
    }
}
