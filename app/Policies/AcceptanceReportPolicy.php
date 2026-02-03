<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AcceptanceReport;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class AcceptanceReportPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view requests');
    }

    public function view(User $user, AcceptanceReport $acceptanceReport): bool
    {
        return $user->belongsToTeam($acceptanceReport->request->team)
            && $user->hasPermissionTo('view requests');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create requests');
    }

    public function update(User $user, AcceptanceReport $acceptanceReport): bool
    {
        return $user->belongsToTeam($acceptanceReport->request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function delete(User $user, AcceptanceReport $acceptanceReport): bool
    {
        return $user->belongsToTeam($acceptanceReport->request->team)
            && $user->hasPermissionTo('delete requests');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete requests');
    }

    public function restore(User $user, AcceptanceReport $acceptanceReport): bool
    {
        return $user->belongsToTeam($acceptanceReport->request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function forceDelete(User $user, AcceptanceReport $acceptanceReport): bool
    {
        return $user->belongsToTeam($acceptanceReport->request->team)
            && $user->hasPermissionTo('delete requests');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete requests');
    }
}
