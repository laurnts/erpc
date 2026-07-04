<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Enums\CentralPurchasingRole;
use App\Models\Membership;
use App\Models\Team;
use Laravel\Jetstream\Events\TeamMemberUpdated;

final readonly class UpdateTeamMemberRole
{
    /**
     * Update a team member's role and central purchasing pivot data.
     */
    public function execute(
        Team $team,
        Membership $membership,
        string $role,
        ?string $centralPurchasingRole = null,
        bool $isApprover = false,
    ): void {
        $pivotData = [
            'role' => $role,
        ];

        if ($role === 'central_purchasing') {
            $pivotData['central_purchasing_role'] = $centralPurchasingRole;
            $pivotData['is_approver'] = $centralPurchasingRole === CentralPurchasingRole::FINANCE->value
                ? $isApprover
                : false;
        } else {
            $pivotData['central_purchasing_role'] = null;
            $pivotData['is_approver'] = false;
        }

        $team->users()->updateExistingPivot($membership->user_id, $pivotData);

        TeamMemberUpdated::dispatch($team->fresh(), $membership->fresh());
    }
}
