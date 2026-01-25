<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationEvaluation>
 */
final class QuotationEvaluationFactory extends Factory
{
    protected $model = QuotationEvaluation::class;

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
            'qe_number' => fn () => sprintf('%03d-DS/QE/%s/%d', $this->faker->numberBetween(1, 999), $this->romanMonth(), now()->year),
            'description' => $this->faker->optional()->sentence(),
            'qe_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'data' => [
                'items' => [],
                'suppliers' => [],
                'request' => [],
            ],
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
}
