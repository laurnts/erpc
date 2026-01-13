<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BuyerQuoteExtension;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerQuoteExtensionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view buyer quotes');
    }

    public function view(User $user, BuyerQuoteExtension $extension): bool
    {
        return $user->belongsToTeam($extension->buyerQuote->team)
            && $user->hasPermissionTo('view buyer quotes');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function update(User $user, BuyerQuoteExtension $extension): bool
    {
        return $user->belongsToTeam($extension->buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function delete(User $user, BuyerQuoteExtension $extension): bool
    {
        return $user->belongsToTeam($extension->buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function restore(User $user, BuyerQuoteExtension $extension): bool
    {
        return $user->belongsToTeam($extension->buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function forceDelete(User $user, BuyerQuoteExtension $extension): bool
    {
        return $user->belongsToTeam($extension->buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer quotes');
    }
}
