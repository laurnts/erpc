<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\TaxCode;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;

/**
 * @extends Factory<Article>
 */
final class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Use only valid Unit enum values
        $units = ['pcs', 'kg', 'mt', 'set', 'box', 'roll', 'pair', 'l', 'm'];

        return [
            'code' => strtoupper($this->faker->unique()->lexify('ART-????')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'sku' => $this->faker->optional()->numerify('SKU-######'),
            'unit' => $this->faker->randomElement($units),
            'default_tax_code_id' => null,
            'attributes' => null,
            'notes' => $this->faker->optional()->paragraph(),
            'is_active' => true,
            'team_id' => Team::factory(),
            'creator_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the article is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Attach a default tax code to the article.
     */
    public function withTaxCode(?TaxCode $taxCode = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'default_tax_code_id' => $taxCode->id ?? TaxCode::factory(),
        ]);
    }

    /**
     * Set custom attributes for the article.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withAttributes(array $attributes): static
    {
        return $this->state(fn (array $state): array => [
            'attributes' => $attributes,
        ]);
    }

    /**
     * Create an article with common attributes like color and size.
     */
    public function withCommonAttributes(): static
    {
        return $this->state(fn (array $attributes): array => [
            'attributes' => [
                'color' => $this->faker->colorName(),
                'size' => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
                'weight' => $this->faker->randomFloat(2, 0.1, 100),
            ],
        ]);
    }

    /**
     * Create an article with a specific unit.
     */
    public function withUnit(string $unit): static
    {
        return $this->state(fn (array $attributes): array => [
            'unit' => $unit,
        ]);
    }

    /**
     * Create an article with a specific SKU.
     */
    public function withSku(string $sku): static
    {
        return $this->state(fn (array $attributes): array => [
            'sku' => $sku,
        ]);
    }

    /**
     * Attach generated product images after creation.
     */
    public function withProductImages(int $count = 1): static
    {
        return $this->afterCreating(function (Article $article) use ($count): void {
            for ($i = 1; $i <= $count; $i++) {
                $article->addMedia(UploadedFile::fake()->image(sprintf('product-%d.jpg', $i), 600, 600))
                    ->toMediaCollection('product_images');
            }
        });
    }
}
