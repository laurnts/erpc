<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BuyerQuote;
use App\Models\ProfitAndLoss;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfitAndLoss>
 */
final class ProfitAndLossFactory extends Factory
{
    protected $model = ProfitAndLoss::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'request_id' => Request::factory(),
            'pnl_number' => fn (): string => sprintf('%04d/EL-PNL/%s/%d', $this->faker->numberBetween(1, 9999), $this->romanMonth(), now()->year),
            'description' => $this->faker->optional()->sentence(),
            'pnl_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'data' => null,
            'creator_id' => User::factory(),
        ];
    }

    /**
     * Get Roman numeral for current month.
     */
    private function romanMonth(): string
    {
        $romans = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        return $romans[now()->month - 1];
    }

    /**
     * Associate with a specific request.
     */
    public function forRequest(Request $request): static
    {
        return $this->state(fn (array $attributes): array => [
            'request_id' => $request->getKey(),
            'team_id' => $request->team_id,
        ]);
    }

    /**
     * Associate with a specific buyer quote.
     */
    public function forBuyerQuote(BuyerQuote $buyerQuote): static
    {
        return $this->state(fn (array $attributes): array => [
            'buyer_quote_id' => $buyerQuote->getKey(),
            'request_id' => $buyerQuote->request_id,
            'team_id' => $buyerQuote->team_id,
        ]);
    }
}
