<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\Company;
use App\Models\SupplierArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierArticle>
 */
final class SupplierArticleFactory extends Factory
{
    protected $model = SupplierArticle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'supplier_id' => Company::factory()->supplier(),
            'supplier_sku' => strtoupper($this->faker->bothify('SKU-####??')),
            'is_preferred' => false,
            'is_active' => true,
        ];
    }

    public function preferred(): static
    {
        return $this->state(fn (): array => [
            'is_preferred' => true,
        ]);
    }

    public function withOffer(string $price = '100.0000', ?float $quantity = 50): static
    {
        return $this->state(fn (): array => [
            'supplier_price' => $price,
            'available_quantity' => $quantity,
        ]);
    }
}
