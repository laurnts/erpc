<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CompanyPortalUser;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class CompanyPortalUserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->currentTeam !== null;
    }

    public function view(User $user, CompanyPortalUser $portalUser): bool
    {
        return $user->belongsToTeam($portalUser->team);
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->currentTeam !== null;
    }

    public function update(User $user, CompanyPortalUser $portalUser): bool
    {
        return $user->belongsToTeam($portalUser->team);
    }

    /**
     * Deleting a membership is revoking an invitation — only Invited-state
     * rows (no linked user) qualify; Active/Deactivated rows are toggled via
     * update instead so the person's history is kept.
     */
    public function delete(User $user, CompanyPortalUser $portalUser): bool
    {
        return $user->belongsToTeam($portalUser->team)
            && $portalUser->user_id === null;
    }

    public function deleteAny(): bool
    {
        return false;
    }

    public function restore(): bool
    {
        return false;
    }

    public function restoreAny(): bool
    {
        return false;
    }

    public function forceDelete(): bool
    {
        return false;
    }

    public function forceDeleteAny(): bool
    {
        return false;
    }
}
