<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PortalRegistrationRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class PortalRegistrationRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->currentTeam !== null;
    }

    public function view(User $user, PortalRegistrationRequest $application): bool
    {
        return $user->belongsToTeam($application->team);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PortalRegistrationRequest $application): bool
    {
        return $user->belongsToTeam($application->team);
    }

    public function delete(User $user, PortalRegistrationRequest $application): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, PortalRegistrationRequest $application): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, PortalRegistrationRequest $application): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
