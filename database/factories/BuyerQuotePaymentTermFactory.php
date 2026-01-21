<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BuyerQuote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BuyerQuotePaymentTerm>
 */
class BuyerQuotePaymentTermFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_quote_id' => BuyerQuote::factory(),
            'due_days' => fake()->numberBetween(0, 90),
            'percentage' => fake()->numberBetween(0, 100),
            'sort_order' => 0,
        ];
    }
}
