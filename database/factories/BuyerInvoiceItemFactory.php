<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\BuyerInvoice;
use App\Models\BuyerInvoiceItem;
use App\Models\BuyerOrderItem;
use App\Models\RequestItem;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerInvoiceItem>
 */
final class BuyerInvoiceItemFactory extends Factory
{
    protected $model = BuyerInvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(4, 1, 100);
        $unitPrice = $this->faker->randomFloat(4, 10, 1000);
        $taxRate = $this->faker->randomElement([0, 5, 10, 15, 20]);

        $lineSubtotal = $quantity * $unitPrice;
        $lineTax = $lineSubtotal * ($taxRate / 100);
        $lineTotal = $lineSubtotal + $lineTax;

        return [
            'buyer_invoice_id' => BuyerInvoice::factory(),
            'buyer_order_item_id' => null,
            'request_item_id' => null,
            'article_id' => null,
            'description' => $this->faker->sentence(3),
            'quantity' => (string) $quantity,
            'unit' => $this->faker->randomElement(['pcs', 'kg', 'm', 'l', 'box']),
            'unit_price' => (string) $unitPrice,
            'tax_code_id' => null,
            'tax_rate' => (string) $taxRate,
            'tax_inclusive' => false,
            'line_subtotal' => (string) round($lineSubtotal, 4),
            'line_tax' => (string) round($lineTax, 4),
            'line_total' => (string) round($lineTotal, 4),
            'sort_order' => 0,
        ];
    }

    /**
     * Set a specific buyer invoice.
     */
    public function forBuyerInvoice(?BuyerInvoice $buyerInvoice = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_invoice_id' => $buyerInvoice ?? BuyerInvoice::factory(),
        ]);
    }

    /**
     * Set a source buyer order item.
     */
    public function forBuyerOrderItem(?BuyerOrderItem $buyerOrderItem = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_order_item_id' => $buyerOrderItem ?? BuyerOrderItem::factory(),
        ]);
    }

    /**
     * Set a source request item.
     */
    public function forRequestItem(?RequestItem $requestItem = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'request_item_id' => $requestItem ?? RequestItem::factory(),
        ]);
    }

    /**
     * Set a specific article.
     */
    public function forArticle(?Article $article = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'article_id' => $article ?? Article::factory(),
        ]);
    }

    /**
     * Set a specific tax code.
     */
    public function withTaxCode(?TaxCode $taxCode = null): static
    {
        return $this->state(function (array $attributes) use ($taxCode): array {
            $code = $taxCode;
            $rate = $code instanceof \App\Models\TaxCode ? (string) $code->rate : ($attributes['tax_rate'] ?? '0.0000');

            // Recalculate totals with new tax rate
            $quantity = (float) $attributes['quantity'];
            $unitPrice = (float) $attributes['unit_price'];
            $taxRateFloat = (float) $rate;

            $lineSubtotal = $quantity * $unitPrice;
            $lineTax = $lineSubtotal * ($taxRateFloat / 100);
            $lineTotal = $lineSubtotal + $lineTax;

            return [
                'tax_code_id' => $code?->getKey(),
                'tax_rate' => $rate,
                'line_subtotal' => (string) round($lineSubtotal, 4),
                'line_tax' => (string) round($lineTax, 4),
                'line_total' => (string) round($lineTotal, 4),
            ];
        });
    }

    /**
     * Set as tax inclusive pricing.
     */
    public function taxInclusive(): static
    {
        return $this->state(function (array $attributes): array {
            // Recalculate with tax inclusive
            $quantity = (float) $attributes['quantity'];
            $unitPrice = (float) $attributes['unit_price'];
            $taxRate = (float) $attributes['tax_rate'];

            $unitPriceExcTax = $taxRate > 0 ? $unitPrice / (1 + ($taxRate / 100)) : $unitPrice;
            $lineSubtotal = $quantity * $unitPriceExcTax;
            $lineTax = ($quantity * $unitPrice) - $lineSubtotal;
            $lineTotal = $quantity * $unitPrice;

            return [
                'tax_inclusive' => true,
                'line_subtotal' => (string) round($lineSubtotal, 4),
                'line_tax' => (string) round($lineTax, 4),
                'line_total' => (string) round($lineTotal, 4),
            ];
        });
    }

    /**
     * Set specific pricing.
     */
    public function withPricing(float $unitPrice, float $quantity, float $taxRate = 0, bool $taxInclusive = false): static
    {
        return $this->state(function (array $attributes) use ($unitPrice, $quantity, $taxRate, $taxInclusive): array {
            if ($taxInclusive) {
                $unitPriceExcTax = $taxRate > 0 ? $unitPrice / (1 + ($taxRate / 100)) : $unitPrice;
                $lineSubtotal = $quantity * $unitPriceExcTax;
                $lineTax = ($quantity * $unitPrice) - $lineSubtotal;
                $lineTotal = $quantity * $unitPrice;
            } else {
                $lineSubtotal = $quantity * $unitPrice;
                $lineTax = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $lineTax;
            }

            return [
                'quantity' => (string) $quantity,
                'unit_price' => (string) $unitPrice,
                'tax_rate' => (string) $taxRate,
                'tax_inclusive' => $taxInclusive,
                'line_subtotal' => (string) round($lineSubtotal, 4),
                'line_tax' => (string) round($lineTax, 4),
                'line_total' => (string) round($lineTotal, 4),
            ];
        });
    }

    /**
     * Set sort order.
     */
    public function withSortOrder(int $sortOrder): static
    {
        return $this->state(fn (array $attributes): array => [
            'sort_order' => $sortOrder,
        ]);
    }
}
