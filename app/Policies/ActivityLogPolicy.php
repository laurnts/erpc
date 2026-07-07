<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Event logs are read-only and restricted to team administrators.
 */
final readonly class ActivityLogPolicy
{
    use HandlesAuthorization;

    private function isAdmin(User $user): bool
    {
        $team = Filament::getTenant();

        return $team !== null
            && $user->hasVerifiedEmail()
            && $user->hasTeamRole($team, 'admin');
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }
}
