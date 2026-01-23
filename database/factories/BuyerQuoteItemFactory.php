<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\RequestItem;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerQuoteItem>
 */
final class BuyerQuoteItemFactory extends Factory
{
    protected $model = BuyerQuoteItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $costPrice = $this->faker->randomFloat(2, 10, 1000);
        $marginPercent = $this->faker->randomFloat(2, 10, 50);
        $unitPrice = $costPrice * (1 + $marginPercent / 100);
        $quantity = $this->faker->randomFloat(2, 1, 100);
        $taxRate = $this->faker->randomElement([0, 10, 11, 12]);

        // Calculate line totals
        $lineSubtotal = $quantity * $unitPrice;
        $lineTax = $lineSubtotal * ($taxRate / 100);
        $lineTotal = $lineSubtotal + $lineTax;

        return [
            'buyer_quote_id' => BuyerQuote::factory(),
            'request_item_id' => null,
            'article_id' => null,
            'supplier_quote_item_id' => null,
            'description' => $this->faker->sentence(4),
            'quantity' => (string) $quantity,
            // Use only valid Unit enum values
            'unit' => $this->faker->randomElement(['pcs', 'kg', 'mt', 'set', 'box', 'roll', 'pair', 'l', 'm']),
            'cost_price' => (string) $costPrice,
            'unit_price' => (string) $unitPrice,
            'unit_price_exc_tax' => (string) $unitPrice,
            'margin_amount' => (string) ($unitPrice - $costPrice),
            'margin_percent' => (string) $marginPercent,
            'tax_code_id' => null,
            'is_tax_inclusive' => false,
            'tax_rate' => (string) $taxRate,
            'tax_amount' => (string) ($lineTax / max($quantity, 0.0001)),
            'line_subtotal' => (string) $lineSubtotal,
            'line_tax' => (string) $lineTax,
            'line_total' => (string) $lineTotal,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Set the buyer quote this item belongs to.
     */
    public function forBuyerQuote(?BuyerQuote $buyerQuote = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_quote_id' => $buyerQuote ?? BuyerQuote::factory(),
        ]);
    }

    /**
     * Link to a request item.
     */
    public function forRequestItem(?RequestItem $requestItem = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'request_item_id' => $requestItem ?? RequestItem::factory(),
        ]);
    }

    /**
     * Link to an article.
     */
    public function forArticle(?Article $article = null): static
    {
        return $this->state(function (array $attributes) use ($article): array {
            $article ??= Article::factory()->create();

            return [
                'article_id' => $article->getKey(),
                'description' => $article->name,
            ];
        });
    }

    /**
     * Set a specific tax code.
     */
    public function withTaxCode(?TaxCode $taxCode = null): static
    {
        return $this->state(function (array $attributes) use ($taxCode): array {
            $taxCode ??= TaxCode::factory()->create();

            return [
                'tax_code_id' => $taxCode->getKey(),
                'tax_rate' => (string) $taxCode->rate,
            ];
        });
    }

    /**
     * Set tax inclusive pricing.
     */
    public function taxInclusive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_tax_inclusive' => true,
        ]);
    }

    /**
     * Set tax exclusive pricing.
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
    public function withPricing(float $costPrice, float $unitPrice, float $quantity = 1): static
    {
        return $this->state(function (array $attributes) use ($costPrice, $unitPrice, $quantity): array {
            $taxRate = (float) ($attributes['tax_rate'] ?? 0);
            $lineSubtotal = $quantity * $unitPrice;
            $lineTax = $lineSubtotal * ($taxRate / 100);
            $lineTotal = $lineSubtotal + $lineTax;
            $marginAmount = $unitPrice - $costPrice;
            $marginPercent = $costPrice > 0 ? ($marginAmount / $costPrice) * 100 : 0;

            return [
                'quantity' => (string) $quantity,
                'cost_price' => (string) $costPrice,
                'unit_price' => (string) $unitPrice,
                'unit_price_exc_tax' => (string) $unitPrice,
                'margin_amount' => (string) $marginAmount,
                'margin_percent' => (string) $marginPercent,
                'line_subtotal' => (string) $lineSubtotal,
                'line_tax' => (string) $lineTax,
                'line_total' => (string) $lineTotal,
            ];
        });
    }

    /**
     * Set a specific margin percentage.
     */
    public function withMarginPercent(float $marginPercent): static
    {
        return $this->state(function (array $attributes) use ($marginPercent): array {
            $costPrice = (float) ($attributes['cost_price'] ?? 100);
            $unitPrice = $costPrice * (1 + $marginPercent / 100);
            $quantity = (float) ($attributes['quantity'] ?? 1);
            $taxRate = (float) ($attributes['tax_rate'] ?? 0);
            $lineSubtotal = $quantity * $unitPrice;
            $lineTax = $lineSubtotal * ($taxRate / 100);
            $lineTotal = $lineSubtotal + $lineTax;

            return [
                'unit_price' => (string) $unitPrice,
                'unit_price_exc_tax' => (string) $unitPrice,
                'margin_amount' => (string) ($unitPrice - $costPrice),
                'margin_percent' => (string) $marginPercent,
                'line_subtotal' => (string) $lineSubtotal,
                'line_tax' => (string) $lineTax,
                'line_total' => (string) $lineTotal,
            ];
        });
    }

    /**
     * Set zero margin (pass-through pricing).
     */
    public function zeroMargin(): static
    {
        return $this->state(function (array $attributes): array {
            $costPrice = (float) ($attributes['cost_price'] ?? 100);
            $quantity = (float) ($attributes['quantity'] ?? 1);
            $taxRate = (float) ($attributes['tax_rate'] ?? 0);
            $lineSubtotal = $quantity * $costPrice;
            $lineTax = $lineSubtotal * ($taxRate / 100);
            $lineTotal = $lineSubtotal + $lineTax;

            return [
                'unit_price' => (string) $costPrice,
                'unit_price_exc_tax' => (string) $costPrice,
                'margin_amount' => '0.0000',
                'margin_percent' => '0.0000',
                'line_subtotal' => (string) $lineSubtotal,
                'line_tax' => (string) $lineTax,
                'line_total' => (string) $lineTotal,
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
