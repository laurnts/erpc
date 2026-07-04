<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PortalRegistrationStatus;
use App\Models\PortalRegistrationRequest;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<PortalRegistrationRequest>
 */
final class PortalRegistrationRequestFactory extends Factory
{
    protected $model = PortalRegistrationRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'company_name' => $this->faker->company(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'message' => $this->faker->optional()->sentence(),
            'password' => Hash::make('password'),
            'status' => PortalRegistrationStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PortalRegistrationStatus::Approved,
            'decided_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => PortalRegistrationStatus::Rejected,
            'decided_at' => now(),
        ]);
    }
}
