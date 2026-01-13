<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\RequestItem;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\SupplierOrderItem;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierInvoiceItem>
 */
final class SupplierInvoiceItemFactory extends Factory
{
    protected $model = SupplierInvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 100);
        $unitPrice = $this->faker->randomFloat(2, 10, 1000);
        $taxRate = $this->faker->randomElement([0, 10, 11, 15, 20]);
        $taxInclusive = $this->faker->boolean(30);

        // Calculate line totals
        $lineAmount = $quantity * $unitPrice;
        if ($taxInclusive) {
            $lineSubtotal = $lineAmount / (1 + $taxRate / 100);
            $lineTax = $lineAmount - $lineSubtotal;
            $lineTotal = $lineAmount;
        } else {
            $lineSubtotal = $lineAmount;
            $lineTax = $lineAmount * $taxRate / 100;
            $lineTotal = $lineSubtotal + $lineTax;
        }

        return [
            'supplier_invoice_id' => SupplierInvoice::factory(),
            'supplier_order_item_id' => null,
            'request_item_id' => null,
            'article_id' => null,
            'description' => $this->faker->sentence(),
            'quantity' => (string) $quantity,
            'unit' => $this->faker->randomElement(['pcs', 'set', 'box', 'kg', 'm']),
            'unit_price' => (string) $unitPrice,
            'tax_code_id' => null,
            'tax_rate' => (string) $taxRate,
            'tax_inclusive' => $taxInclusive,
            'line_subtotal' => (string) round($lineSubtotal, 4),
            'line_tax' => (string) round($lineTax, 4),
            'line_total' => (string) round($lineTotal, 4),
            'sort_order' => 0,
        ];
    }

    /**
     * Associate with a specific supplier invoice.
     */
    public function forSupplierInvoice(?SupplierInvoice $invoice = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'supplier_invoice_id' => $invoice ?? SupplierInvoice::factory(),
        ]);
    }

    /**
     * Associate with a specific supplier order item.
     */
    public function forSupplierOrderItem(?SupplierOrderItem $orderItem = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'supplier_order_item_id' => $orderItem ?? SupplierOrderItem::factory(),
        ]);
    }

    /**
     * Associate with a specific request item.
     */
    public function forRequestItem(?RequestItem $requestItem = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'request_item_id' => $requestItem ?? RequestItem::factory(),
        ]);
    }

    /**
     * Associate with a specific article.
     */
    public function forArticle(?Article $article = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'article_id' => $article ?? Article::factory(),
        ]);
    }

    /**
     * Associate with a specific tax code.
     */
    public function withTaxCode(?TaxCode $taxCode = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'tax_code_id' => $taxCode ?? TaxCode::factory(),
        ]);
    }

    /**
     * Set as tax inclusive.
     */
    public function taxInclusive(): static
    {
        return $this->state(function (array $attributes): array {
            $quantity = (float) ($attributes['quantity'] ?? 1);
            $unitPrice = (float) ($attributes['unit_price'] ?? 100);
            $taxRate = (float) ($attributes['tax_rate'] ?? 10);

            $lineAmount = $quantity * $unitPrice;
            $lineSubtotal = $lineAmount / (1 + $taxRate / 100);
            $lineTax = $lineAmount - $lineSubtotal;

            return [
                'tax_inclusive' => true,
                'line_subtotal' => (string) round($lineSubtotal, 4),
                'line_tax' => (string) round($lineTax, 4),
                'line_total' => (string) round($lineAmount, 4),
            ];
        });
    }

    /**
     * Set as tax exclusive.
     */
    public function taxExclusive(): static
    {
        return $this->state(function (array $attributes): array {
            $quantity = (float) ($attributes['quantity'] ?? 1);
            $unitPrice = (float) ($attributes['unit_price'] ?? 100);
            $taxRate = (float) ($attributes['tax_rate'] ?? 10);

            $lineSubtotal = $quantity * $unitPrice;
            $lineTax = $lineSubtotal * $taxRate / 100;
            $lineTotal = $lineSubtotal + $lineTax;

            return [
                'tax_inclusive' => false,
                'line_subtotal' => (string) round($lineSubtotal, 4),
                'line_tax' => (string) round($lineTax, 4),
                'line_total' => (string) round($lineTotal, 4),
            ];
        });
    }

    /**
     * Set with specific tax rate.
     */
    public function withTaxRate(float $rate): static
    {
        return $this->state(function (array $attributes) use ($rate): array {
            $quantity = (float) ($attributes['quantity'] ?? 1);
            $unitPrice = (float) ($attributes['unit_price'] ?? 100);
            $taxInclusive = $attributes['tax_inclusive'] ?? false;

            $lineAmount = $quantity * $unitPrice;
            if ($taxInclusive) {
                $lineSubtotal = $lineAmount / (1 + $rate / 100);
                $lineTax = $lineAmount - $lineSubtotal;
                $lineTotal = $lineAmount;
            } else {
                $lineSubtotal = $lineAmount;
                $lineTax = $lineAmount * $rate / 100;
                $lineTotal = $lineSubtotal + $lineTax;
            }

            return [
                'tax_rate' => (string) $rate,
                'line_subtotal' => (string) round($lineSubtotal, 4),
                'line_tax' => (string) round($lineTax, 4),
                'line_total' => (string) round($lineTotal, 4),
            ];
        });
    }

    /**
     * Set zero tax.
     */
    public function zeroTax(): static
    {
        return $this->state(function (array $attributes): array {
            $quantity = (float) ($attributes['quantity'] ?? 1);
            $unitPrice = (float) ($attributes['unit_price'] ?? 100);
            $lineTotal = $quantity * $unitPrice;

            return [
                'tax_rate' => '0.0000',
                'tax_inclusive' => false,
                'line_subtotal' => (string) round($lineTotal, 4),
                'line_tax' => '0.0000',
                'line_total' => (string) round($lineTotal, 4),
            ];
        });
    }

    /**
     * Set specific amounts.
     */
    public function withAmounts(float $quantity, float $unitPrice): static
    {
        return $this->state(function (array $attributes) use ($quantity, $unitPrice): array {
            $taxRate = (float) ($attributes['tax_rate'] ?? 10);
            $taxInclusive = $attributes['tax_inclusive'] ?? false;

            $lineAmount = $quantity * $unitPrice;
            if ($taxInclusive) {
                $lineSubtotal = $lineAmount / (1 + $taxRate / 100);
                $lineTax = $lineAmount - $lineSubtotal;
                $lineTotal = $lineAmount;
            } else {
                $lineSubtotal = $lineAmount;
                $lineTax = $lineAmount * $taxRate / 100;
                $lineTotal = $lineSubtotal + $lineTax;
            }

            return [
                'quantity' => (string) $quantity,
                'unit_price' => (string) $unitPrice,
                'line_subtotal' => (string) round($lineSubtotal, 4),
                'line_tax' => (string) round($lineTax, 4),
                'line_total' => (string) round($lineTotal, 4),
            ];
        });
    }
}
