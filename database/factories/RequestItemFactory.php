<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\Request;
use App\Models\RequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestItem>
 */
final class RequestItemFactory extends Factory
{
    protected $model = RequestItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => Request::factory(),
            'article_id' => null,
            'description' => $this->faker->sentence(3),
            'quantity' => $this->faker->randomFloat(2, 1, 100),
            'unit' => $this->faker->randomElement(['pcs', 'kg', 'm', 'l', 'box', 'set']),
            'notes' => $this->faker->optional()->sentence(),
            'sort_order' => $this->faker->numberBetween(0, 10),
            'is_matched' => false,
        ];
    }

    /**
     * Indicate that the item is matched to an article.
     */
    public function matched(?Article $article = null): static
    {
        return $this->state(function (array $attributes) use ($article): array {
            $matchedArticle = $article ?? Article::factory()->create();

            return [
                'article_id' => $matchedArticle->id,
                'is_matched' => true,
                'description' => $matchedArticle->name,
            ];
        });
    }

    /**
     * Indicate that the item is matched with a specific article.
     */
    public function forArticle(Article $article): static
    {
        return $this->state(fn (array $attributes): array => [
            'article_id' => $article->id,
            'is_matched' => true,
            'description' => $article->name,
        ]);
    }

    /**
     * Indicate that the item is unmatched (vague capture).
     */
    public function unmatched(): static
    {
        return $this->state(fn (array $attributes): array => [
            'article_id' => null,
            'is_matched' => false,
        ]);
    }

    /**
     * Set specific quantity and unit.
     */
    public function withQuantity(float $quantity, string $unit = 'pcs'): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity' => $quantity,
            'unit' => $unit,
        ]);
    }

    /**
     * Set the sort order.
     */
    public function withSortOrder(int $sortOrder): static
    {
        return $this->state(fn (array $attributes): array => [
            'sort_order' => $sortOrder,
        ]);
    }
}
