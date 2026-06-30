<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CentralPurchasingRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
final class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'role' => 'central_purchasing',
            'central_purchasing_role' => CentralPurchasingRole::FINANCE,
            'is_approver' => false,
        ];
    }
}
