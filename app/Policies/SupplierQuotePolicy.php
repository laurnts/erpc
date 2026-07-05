<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SupplierQuoteStatus;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Policies\Concerns\ResolvesPanelContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class SupplierQuotePolicy
{
    use HandlesAuthorization;
    use ResolvesPanelContext;

    /**
     * Check if user is an administrator for the current team.
     */
    private function isAdmin(User $user): bool
    {
        $team = Filament::getTenant();

        return $team !== null && $user->hasTeamRole($team, 'admin');
    }

    /**
     * Supplier-panel ownership: the quote belongs to one of the user's active
     * supplier-portal companies (portal-typed membership, recomputed live).
     */
    private function ownsAsSupplier(User $user, SupplierQuote $supplierQuote): bool
    {
        return $this->userOwnsSupplierCompany($user, $supplierQuote->supplier_id);
    }

    public function viewAny(User $user): bool
    {
        if ($this->isSupplierPanel()) {
            return $user->hasActiveSupplierPortalAccess();
        }

        return $user->hasVerifiedEmail() && $user->currentTeam !== null;
    }

    public function view(User $user, SupplierQuote $supplierQuote): bool
    {
        if ($this->isSupplierPanel()) {
            return $this->ownsAsSupplier($user, $supplierQuote)
                && $supplierQuote->sent_to_supplier_at !== null;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierQuote->team);
        }

        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('view supplier quotes');
    }

    /**
     * Portal submission gate: own sent, pending, undeclined, unexpired quote.
     */
    public function submit(User $user, SupplierQuote $supplierQuote): bool
    {
        if (! $this->isSupplierPanel()) {
            return false;
        }

        return $this->ownsAsSupplier($user, $supplierQuote)
            && $supplierQuote->status === SupplierQuoteStatus::PENDING
            && $supplierQuote->sent_to_supplier_at !== null
            && $supplierQuote->declined_at === null
            && ! $supplierQuote->is_expired;
    }

    /**
     * Portal decline gate: own sent, pending, not-yet-declined, unexpired quote.
     */
    public function decline(User $user, SupplierQuote $supplierQuote): bool
    {
        if (! $this->isSupplierPanel()) {
            return false;
        }

        return $this->ownsAsSupplier($user, $supplierQuote)
            && $supplierQuote->status === SupplierQuoteStatus::PENDING
            && $supplierQuote->sent_to_supplier_at !== null
            && $supplierQuote->declined_at === null
            && ! $supplierQuote->is_expired;
    }

    public function create(User $user): bool
    {
        if ($this->isSupplierPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create supplier quotes');
    }

    public function update(User $user, SupplierQuote $supplierQuote): bool
    {
        if ($this->isSupplierPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierQuote->team);
        }

        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function delete(User $user, SupplierQuote $supplierQuote): bool
    {
        if ($this->isSupplierPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierQuote->team);
        }

        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('delete supplier quotes');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isSupplierPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete supplier quotes');
    }

    public function restore(User $user, SupplierQuote $supplierQuote): bool
    {
        if ($this->isSupplierPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierQuote->team);
        }

        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isSupplierPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function forceDelete(User $user, SupplierQuote $supplierQuote): bool
    {
        if ($this->isSupplierPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierQuote->team);
        }

        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('delete supplier quotes');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isSupplierPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete supplier quotes');
    }
}
