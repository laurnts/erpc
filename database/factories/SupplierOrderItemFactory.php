<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\RequestItem;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\SupplierQuoteItem;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierOrderItem>
 */
final class SupplierOrderItemFactory extends Factory
{
    protected $model = SupplierOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 100);
        $unitPrice = $this->faker->randomFloat(2, 10, 10000);
        $taxRate = $this->faker->randomElement([0, 10, 11, 15, 20]);
        $isTaxInclusive = $this->faker->boolean(30);

        // Calculate totals
        $lineAmount = $quantity * $unitPrice;
        if ($isTaxInclusive) {
            $lineTotal = $lineAmount;
            $unitPriceExcTax = $unitPrice / (1 + $taxRate / 100);
            $taxAmount = $unitPrice - $unitPriceExcTax;
        } else {
            $lineTax = $lineAmount * $taxRate / 100;
            $lineTotal = $lineAmount + $lineTax;
            $unitPriceExcTax = $unitPrice;
            $taxAmount = $unitPrice * $taxRate / 100;
        }

        return [
            'supplier_order_id' => SupplierOrder::factory(),
            'supplier_quote_item_id' => null,
            'request_item_id' => null,
            'article_id' => null,
            'description' => $this->faker->sentence(4),
            'quantity' => (string) round($quantity, 4),
            'unit' => $this->faker->randomElement(['pcs', 'kg', 'm', 'set', 'box']),
            'unit_price' => (string) round($unitPrice, 4),
            'unit_price_exc_tax' => (string) round($unitPriceExcTax, 4),
            'tax_code_id' => null,
            'is_tax_inclusive' => $isTaxInclusive,
            'tax_rate' => (string) $taxRate,
            'tax_amount' => (string) round($taxAmount, 4),
            'line_total' => (string) round($lineTotal, 4),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Associate with a specific supplier order.
     */
    public function forSupplierOrder(?SupplierOrder $supplierOrder = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'supplier_order_id' => $supplierOrder ?? SupplierOrder::factory(),
        ]);
    }

    /**
     * Associate with a specific supplier quote item (traceability).
     */
    public function fromQuoteItem(?SupplierQuoteItem $quoteItem = null): static
    {
        return $this->state(function (array $attributes) use ($quoteItem): array {
            $item = $quoteItem ?? SupplierQuoteItem::factory()->create();

            return [
                'supplier_quote_item_id' => $item->getKey(),
                'request_item_id' => $item->request_item_id,
                'article_id' => $item->article_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'unit_price_exc_tax' => $item->unit_price_exc_tax,
                'tax_code_id' => $item->tax_code_id,
                'is_tax_inclusive' => $item->is_tax_inclusive,
                'tax_rate' => $item->tax_rate,
                'tax_amount' => $item->tax_amount,
                'line_total' => $item->line_total,
            ];
        });
    }

    /**
     * Associate with a specific request item (traceability).
     */
    public function forRequestItem(?RequestItem $requestItem = null): static
    {
        return $this->state(function (array $attributes) use ($requestItem): array {
            $item = $requestItem ?? RequestItem::factory()->create();

            return [
                'request_item_id' => $item->getKey(),
                'article_id' => $item->article_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
            ];
        });
    }

    /**
     * Associate with a specific article.
     */
    public function forArticle(?Article $article = null): static
    {
        return $this->state(function (array $attributes) use ($article): array {
            $art = $article ?? Article::factory()->create();

            return [
                'article_id' => $art->getKey(),
                'description' => $art->name,
                'unit' => $art->unit,
            ];
        });
    }

    /**
     * Apply a specific tax code.
     */
    public function withTaxCode(?TaxCode $taxCode = null): static
    {
        return $this->state(function (array $attributes) use ($taxCode): array {
            $code = $taxCode ?? TaxCode::factory()->create();

            return [
                'tax_code_id' => $code->getKey(),
                'tax_rate' => (string) $code->rate,
                'is_tax_inclusive' => $code->is_inclusive_default,
            ];
        });
    }

    /**
     * Set tax-inclusive pricing.
     */
    public function taxInclusive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_tax_inclusive' => true,
        ]);
    }

    /**
     * Set tax-exclusive pricing.
     */
    public function taxExclusive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_tax_inclusive' => false,
        ]);
    }

    /**
     * Set specific pricing.
     */
    public function withPricing(float $quantity, float $unitPrice, float $taxRate = 0, bool $isTaxInclusive = false): static
    {
        $lineAmount = $quantity * $unitPrice;
        if ($isTaxInclusive) {
            $lineTotal = $lineAmount;
            $unitPriceExcTax = $unitPrice / (1 + $taxRate / 100);
            $taxAmount = $unitPrice - $unitPriceExcTax;
        } else {
            $lineTax = $lineAmount * $taxRate / 100;
            $lineTotal = $lineAmount + $lineTax;
            $unitPriceExcTax = $unitPrice;
            $taxAmount = $unitPrice * $taxRate / 100;
        }

        return $this->state(fn (array $attributes): array => [
            'quantity' => (string) round($quantity, 4),
            'unit_price' => (string) round($unitPrice, 4),
            'unit_price_exc_tax' => (string) round($unitPriceExcTax, 4),
            'is_tax_inclusive' => $isTaxInclusive,
            'tax_rate' => (string) $taxRate,
            'tax_amount' => (string) round($taxAmount, 4),
            'line_total' => (string) round($lineTotal, 4),
        ]);
    }

    /**
     * Set zero tax.
     */
    public function noTax(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tax_code_id' => null,
            'tax_rate' => '0',
            'tax_amount' => '0',
        ]);
    }

    /**
     * Set sort order.
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes): array => [
            'sort_order' => $position,
        ]);
    }
}
