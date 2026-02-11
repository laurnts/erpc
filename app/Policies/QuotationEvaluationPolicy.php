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
     * Check if user is an administrator for the current team.
     */
    private function isAdmin(User $user): bool
    {
        $team = Filament::getTenant();
        return $team !== null && $user->hasTeamRole($team, 'admin');
    }

    public function viewAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view quotation evaluations');
    }

    public function view(User $user, QuotationEvaluation $quotationEvaluation): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($quotationEvaluation->team);
        }

        return $user->belongsToTeam($quotationEvaluation->team)
            && $user->hasPermissionTo('view quotation evaluations');
    }

    public function create(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create quotation evaluations');
    }

    public function update(User $user, QuotationEvaluation $quotationEvaluation): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($quotationEvaluation->team);
        }

        return $user->belongsToTeam($quotationEvaluation->team)
            && $user->hasPermissionTo('update quotation evaluations');
    }

    public function delete(User $user, QuotationEvaluation $quotationEvaluation): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($quotationEvaluation->team);
        }

        return $user->belongsToTeam($quotationEvaluation->team)
            && $user->hasPermissionTo('delete quotation evaluations');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete quotation evaluations');
    }

    public function restore(User $user, QuotationEvaluation $quotationEvaluation): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($quotationEvaluation->team);
        }

        return $user->belongsToTeam($quotationEvaluation->team)
            && $user->hasPermissionTo('update quotation evaluations');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update quotation evaluations');
    }

    public function forceDelete(User $user, QuotationEvaluation $quotationEvaluation): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($quotationEvaluation->team);
        }

        return $user->belongsToTeam($quotationEvaluation->team)
            && $user->hasPermissionTo('delete quotation evaluations');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete quotation evaluations');
    }
}
