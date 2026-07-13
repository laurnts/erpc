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

    public function create(): bool
    {
        return false;
    }

    public function update(User $user, PortalRegistrationRequest $application): bool
    {
        return $user->belongsToTeam($application->team);
    }

    public function delete(): bool
    {
        return false;
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
