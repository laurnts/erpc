<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProfitAndLoss;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class ProfitAndLossPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProfitAndLoss $profitAndLoss): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false; // P&L are created via workflow, not directly
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProfitAndLoss $profitAndLoss): bool
    {
        return false; // P&L are updated via workflow, not directly
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProfitAndLoss $profitAndLoss): bool
    {
        return false; // P&L cannot be deleted
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProfitAndLoss $profitAndLoss): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProfitAndLoss $profitAndLoss): bool
    {
        return false;
    }
}
