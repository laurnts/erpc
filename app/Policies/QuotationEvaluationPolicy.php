<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\QuotationEvaluation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class QuotationEvaluationPolicy
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
    public function view(User $user, QuotationEvaluation $quotationEvaluation): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        /** @var \App\Models\Team|null $team */
        $team = Filament::getTenant();

        if ($team === null) {
            return false;
        }

        // Administrators can create QEs
        if ($user->hasTeamRole($team, 'admin')) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        // Users who can view supplier quotes can create QEs (as part of the workflow)
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view supplier quotes');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, QuotationEvaluation $quotationEvaluation): bool
    {
        return false; // QEs are updated via workflow, not directly
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QuotationEvaluation $quotationEvaluation): bool
    {
        return false; // QEs cannot be deleted
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, QuotationEvaluation $quotationEvaluation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, QuotationEvaluation $quotationEvaluation): bool
    {
        return false;
    }
}
