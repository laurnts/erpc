<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\BuyerInvoice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerInvoicePolicy
{
    use HandlesAuthorization;

    private function isBuyerPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'buyer';
    }

    private function userCanAccessPortalInvoice(User $user, BuyerInvoice $invoice): bool
    {
        $buyerId = $invoice->request?->buyer_id;

        if ($buyerId === null) {
            return false;
        }

        return in_array($buyerId, $user->activeBuyerPortalCompanyIds(), true);
    }

    public function viewAny(User $user): bool
    {
        if ($this->isBuyerPanel()) {
            return $user->hasActiveBuyerPortalAccess();
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view buyer invoices');
    }

    public function view(User $user, BuyerInvoice $invoice): bool
    {
        if ($this->isBuyerPanel()) {
            return $this->userCanAccessPortalInvoice($user, $invoice)
                && $invoice->status !== InvoiceStatus::DRAFT;
        }

        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('view buyer invoices');
    }

    public function create(User $user): bool
    {
        if ($this->isBuyerPanel()) {
            return false;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create buyer invoices');
    }

    public function update(User $user, BuyerInvoice $invoice): bool
    {
        if ($this->isBuyerPanel()) {
            return false;
        }

        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function delete(User $user, BuyerInvoice $invoice): bool
    {
        if ($this->isBuyerPanel()) {
            return false;
        }

        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('delete buyer invoices');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isBuyerPanel()) {
            return false;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete buyer invoices');
    }

    public function restore(User $user, BuyerInvoice $invoice): bool
    {
        if ($this->isBuyerPanel()) {
            return false;
        }

        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isBuyerPanel()) {
            return false;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function forceDelete(User $user, BuyerInvoice $invoice): bool
    {
        if ($this->isBuyerPanel()) {
            return false;
        }

        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('delete buyer invoices');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isBuyerPanel()) {
            return false;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete buyer invoices');
    }
}
