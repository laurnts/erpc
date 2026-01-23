<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Models\BuyerQuoteItem;
use App\Models\RequestItem;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerOrderItem>
 */
final class BuyerOrderItemFactory extends Factory
{
    protected $model = BuyerOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 10, 1000);
        $quantity = $this->faker->randomFloat(2, 1, 100);
        $taxRate = $this->faker->randomElement([0, 10, 11, 12]);

        // Calculate line totals (assuming tax exclusive)
        $lineSubtotal = $quantity * $unitPrice;
        $lineTax = $lineSubtotal * ($taxRate / 100);
        $lineTotal = $lineSubtotal + $lineTax;
        $taxAmount = $unitPrice * ($taxRate / 100);

        return [
            'buyer_order_id' => BuyerOrder::factory(),
            'buyer_quote_item_id' => null,
            'request_item_id' => null,
            'article_id' => null,
            'description' => $this->faker->sentence(4),
            'quantity' => (string) $quantity,
            'unit' => $this->faker->randomElement(['pcs', 'kg', 'mt', 'set', 'box', 'roll', 'pair', 'l', 'm']),
            'unit_price' => (string) round($unitPrice, 2),
            'unit_price_exc_tax' => (string) round($unitPrice, 2),
            'tax_amount' => (string) round($taxAmount, 2),
            'line_total' => (string) round($lineTotal, 2),
            'tax_code_id' => null,
            'is_tax_inclusive' => false,
            'tax_rate' => (string) $taxRate,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Set the buyer order this item belongs to.
     */
    public function forBuyerOrder(?BuyerOrder $buyerOrder = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_order_id' => $buyerOrder ?? BuyerOrder::factory(),
        ]);
    }

    /**
     * Link to a buyer quote item (source).
     */
    public function forBuyerQuoteItem(?BuyerQuoteItem $buyerQuoteItem = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_quote_item_id' => $buyerQuoteItem ?? BuyerQuoteItem::factory(),
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
    public function withPricing(float $unitPrice, float $quantity = 1, float $taxRate = 0): static
    {
        return $this->state(function (array $attributes) use ($unitPrice, $quantity, $taxRate): array {
            $lineSubtotal = $quantity * $unitPrice;
            $lineTax = $lineSubtotal * ($taxRate / 100);
            $lineTotal = $lineSubtotal + $lineTax;
            $taxAmount = $unitPrice * ($taxRate / 100);

            return [
                'quantity' => (string) $quantity,
                'unit_price' => (string) round($unitPrice, 2),
                'unit_price_exc_tax' => (string) round($unitPrice, 2),
                'tax_amount' => (string) round($taxAmount, 2),
                'line_total' => (string) round($lineTotal, 2),
                'tax_rate' => (string) $taxRate,
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
