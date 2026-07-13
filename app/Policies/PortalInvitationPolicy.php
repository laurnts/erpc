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
    public function create(): bool
    {
        return false;
    }

    public function update(): bool
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
