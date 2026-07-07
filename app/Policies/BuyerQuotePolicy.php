<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BuyerQuoteStatus;
use App\Models\BuyerQuote;
use App\Models\User;
use App\Policies\Concerns\ResolvesPanelContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerQuotePolicy
{
    use HandlesAuthorization;
    use ResolvesPanelContext;

    private function userCanAccessPortalQuote(User $user, BuyerQuote $buyerQuote): bool
    {
        return $this->userOwnsBuyerCompany($user, $buyerQuote->buyer_id);
    }

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
        if ($this->isCustomerPanel()) {
            return $user->hasActiveCustomerPortalAccess();
        }

        return $user->hasVerifiedEmail() && $user->currentTeam !== null;
    }

    public function view(User $user, BuyerQuote $buyerQuote): bool
    {
        if ($this->isCustomerPanel()) {
            return $this->userCanAccessPortalQuote($user, $buyerQuote)
                && $buyerQuote->status !== BuyerQuoteStatus::DRAFT;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerQuote->team);
        }

        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('view buyer quotes');
    }

    public function create(User $user): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create buyer quotes');
    }

    public function update(User $user, BuyerQuote $buyerQuote): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerQuote->team);
        }

        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function delete(User $user, BuyerQuote $buyerQuote): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerQuote->team);
        }

        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('delete buyer quotes');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete buyer quotes');
    }

    public function restore(User $user, BuyerQuote $buyerQuote): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerQuote->team);
        }

        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function forceDelete(User $user, BuyerQuote $buyerQuote): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerQuote->team);
        }

        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('delete buyer quotes');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete buyer quotes');
    }

    /**
     * Customer portal: accept or reject a sent quote.
     */
    public function respond(User $user, BuyerQuote $buyerQuote): bool
    {
        if (! $this->isCustomerPanel()) {
            return false;
        }

        return $this->userCanAccessPortalQuote($user, $buyerQuote)
            && $buyerQuote->status === BuyerQuoteStatus::SENT;
    }

    /**
     * Customer portal: upload purchase order for a sent quote.
     */
    public function uploadPo(User $user, BuyerQuote $buyerQuote): bool
    {
        return $this->respond($user, $buyerQuote);
    }

    /**
     * Determine if the user can send the quote.
     */
    public function send(User $user, BuyerQuote $buyerQuote): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerQuote->team) && $buyerQuote->status->canSend();
        }

        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes')
            && $buyerQuote->status->canSend();
    }

    /**
     * Determine if the user can create a new version.
     */
    public function createVersion(User $user, BuyerQuote $buyerQuote): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerQuote->team) && $buyerQuote->status->canCreateNewVersion();
        }

        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('create buyer quotes')
            && $buyerQuote->status->canCreateNewVersion();
    }

    /**
     * Determine if the user can extend the validity.
     */
    public function extendValidity(User $user, BuyerQuote $buyerQuote): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($buyerQuote->team) && $buyerQuote->status->isActive();
        }

        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes')
            && $buyerQuote->status->isActive();
    }
}
