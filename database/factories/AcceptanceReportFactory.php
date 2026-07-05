<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcceptanceReport;
use App\Models\Request;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcceptanceReport>
 */
final class AcceptanceReportFactory extends Factory
{
    protected $model = AcceptanceReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => Request::factory(),
        ];
    }
}
