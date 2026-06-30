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

    public function delete(User $user, CompanyPortalUser $portalUser): bool
    {
        return $user->belongsToTeam($portalUser->team);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, CompanyPortalUser $portalUser): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, CompanyPortalUser $portalUser): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
