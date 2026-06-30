<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BuyerOrder;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Check if user is an administrator for the current team.
     */
    private function isAdmin(User $user): bool
    {
        $team = Filament::getTenant();

        return $team !== null && $user->hasTeamRole($team, 'admin');
    }

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->currentTeam !== null;
    }

    public function view(User $user, BuyerOrder $buyerOrder): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerOrder->team);
        }

        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('view buyer orders');
    }

    public function create(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create buyer orders');
    }

    public function update(User $user, BuyerOrder $buyerOrder): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerOrder->team) && $buyerOrder->status->canEdit();
        }

        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders')
            && $buyerOrder->status->canEdit();
    }

    public function delete(User $user, BuyerOrder $buyerOrder): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerOrder->team) && $buyerOrder->status->canEdit();
        }

        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('delete buyer orders')
            && $buyerOrder->status->canEdit();
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete buyer orders');
    }

    public function restore(User $user, BuyerOrder $buyerOrder): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerOrder->team);
        }

        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer orders');
    }

    public function forceDelete(User $user, BuyerOrder $buyerOrder): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerOrder->team);
        }

        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('delete buyer orders');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete buyer orders');
    }

    /**
     * Determine if the user can confirm the order.
     */
    public function confirm(User $user, BuyerOrder $buyerOrder): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerOrder->team) && $buyerOrder->status->canConfirm();
        }

        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders')
            && $buyerOrder->status->canConfirm();
    }

    /**
     * Determine if the user can cancel the order.
     */
    public function cancel(User $user, BuyerOrder $buyerOrder): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerOrder->team) && $buyerOrder->status->canCancel();
        }

        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders')
            && $buyerOrder->status->canCancel();
    }

    /**
     * Determine if the user can progress the order status.
     */
    public function progressStatus(User $user, BuyerOrder $buyerOrder): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerOrder->team) && $buyerOrder->status->canProgress();
        }

        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders')
            && $buyerOrder->status->canProgress();
    }
}
