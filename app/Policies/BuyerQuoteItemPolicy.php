<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BuyerQuoteItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerQuoteItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view buyer quotes');
    }

    public function view(User $user, BuyerQuoteItem $item): bool
    {
        return $user->belongsToTeam($item->buyerQuote->team)
            && $user->hasPermissionTo('view buyer quotes');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function update(User $user, BuyerQuoteItem $item): bool
    {
        return $user->belongsToTeam($item->buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function delete(User $user, BuyerQuoteItem $item): bool
    {
        return $user->belongsToTeam($item->buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function restore(User $user, BuyerQuoteItem $item): bool
    {
        return $user->belongsToTeam($item->buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function forceDelete(User $user, BuyerQuoteItem $item): bool
    {
        return $user->belongsToTeam($item->buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function reorder(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer quotes');
    }
}
