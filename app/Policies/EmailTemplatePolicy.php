<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class EmailTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view email templates');
    }

    public function view(User $user, EmailTemplate $emailTemplate): bool
    {
        // Allow viewing if template belongs to user's team or is a default template (team_id is null)
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && ($emailTemplate->team_id === null || $user->belongsToTeam($emailTemplate->team))
            && $user->hasPermissionTo('view email templates');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create email templates');
    }

    public function update(User $user, EmailTemplate $emailTemplate): bool
    {
        // Only allow updating if template belongs to user's team and is not a default template
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && !$emailTemplate->is_default
            && ($emailTemplate->team_id === null || $user->belongsToTeam($emailTemplate->team))
            && $user->hasPermissionTo('update email templates');
    }

    public function delete(User $user, EmailTemplate $emailTemplate): bool
    {
        // Only allow deleting if template belongs to user's team and is not a default template
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && !$emailTemplate->is_default
            && ($emailTemplate->team_id === null || $user->belongsToTeam($emailTemplate->team))
            && $user->hasPermissionTo('delete email templates');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete email templates');
    }

    public function restore(User $user, EmailTemplate $emailTemplate): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && !$emailTemplate->is_default
            && ($emailTemplate->team_id === null || $user->belongsToTeam($emailTemplate->team))
            && $user->hasPermissionTo('update email templates');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update email templates');
    }

    public function forceDelete(User $user, EmailTemplate $emailTemplate): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && !$emailTemplate->is_default
            && ($emailTemplate->team_id === null || $user->belongsToTeam($emailTemplate->team))
            && $user->hasPermissionTo('delete email templates');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete email templates');
    }
}
