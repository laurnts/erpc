<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PortalInvitation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class PortalInvitationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->currentTeam !== null;
    }

    public function view(User $user, PortalInvitation $invitation): bool
    {
        return $user->belongsToTeam($invitation->team);
    }

    /**
     * Invitations are only issued through the invite action on company
     * records, never created directly.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PortalInvitation $invitation): bool
    {
        return false;
    }

    /**
     * Revoking a pending invitation deletes it; accepted invitations are
     * historical records and stay.
     */
    public function delete(User $user, PortalInvitation $invitation): bool
    {
        return $user->belongsToTeam($invitation->team)
            && $invitation->accepted_at === null;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, PortalInvitation $invitation): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, PortalInvitation $invitation): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
