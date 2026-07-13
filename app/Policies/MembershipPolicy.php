<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Membership;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class MembershipPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->currentTeam !== null;
    }

    public function view(User $user, Membership $membership): bool
    {
        $team = Filament::getTenant();

        return $user->belongsToTeam($team) && $membership->team_id === $team->id;
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();

        return $user->hasVerifiedEmail() && $team !== null && $user->ownsTeam($team);
    }

    public function update(User $user, Membership $membership): bool
    {
        $team = Filament::getTenant();

        return $team !== null &&
               $membership->team_id === $team->id &&
               $user->ownsTeam($team);
    }

    public function delete(User $user, Membership $membership): bool
    {
        $team = Filament::getTenant();

        return $team !== null &&
               $membership->team_id === $team->id &&
               $user->ownsTeam($team) &&
               $user->id !== $membership->user_id;
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

    public function forceDelete(User $user, Membership $membership): bool
    {
        $team = Filament::getTenant();

        return $team !== null &&
               $membership->team_id === $team->id &&
               $user->ownsTeam($team);
    }

    public function forceDeleteAny(): bool
    {
        return false;
    }
}
