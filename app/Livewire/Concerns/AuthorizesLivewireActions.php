<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Trait for handling authorization in Livewire components.
 *
 * Provides consistent patterns for:
 * - Checking policy abilities via Laravel Gate
 * - Validating team ownership in multi-tenant context
 * - Standardized authorization error handling
 */
trait AuthorizesLivewireActions
{
    /**
     * Authorize an action against a model or class.
     *
     * @param  class-string|Model  $model
     *
     * @throws AuthorizationException
     */
    protected function authorizeAction(string $ability, string|Model $model): void
    {
        Gate::authorize($ability, $model);
    }

    /**
     * Ensure a model belongs to the current Filament tenant.
     *
     * @throws AuthorizationException
     */
    protected function ensureTeamOwnership(Model $model): void
    {
        if (! $this->belongsToCurrentTeam($model)) {
            throw new AuthorizationException('This record does not belong to your team.');
        }
    }

    /**
     * Check if a model belongs to the current Filament tenant.
     */
    protected function belongsToCurrentTeam(Model $model): bool
    {
        $team = Filament::getTenant();

        if ($team === null) {
            return false;
        }

        /** @var int|null $modelTeamId */
        $modelTeamId = $model->getAttribute('team_id');

        return $modelTeamId === $team->getKey();
    }

    /**
     * Check if the current user can perform an action.
     *
     * @param  class-string|Model  $model
     */
    protected function canPerformAction(string $ability, string|Model $model): bool
    {
        return Gate::allows($ability, $model);
    }

    /**
     * Handle authorization failure with logging and user notification.
     *
     * Logs the failure for security auditing and displays a user-friendly
     * notification via Filament's notification system.
     *
     * @param  class-string|Model  $model
     */
    protected function handleAuthorizationFailure(string $action, string|Model $model): void
    {
        Log::warning('Authorization failure', [
            'user_id' => auth()->id(),
            'action' => $action,
            'model' => is_string($model) ? $model : $model::class,
            'model_id' => $model instanceof Model ? $model->getKey() : null,
        ]);

        Notification::make()
            ->title('Permission Denied')
            ->body('You do not have permission to perform this action.')
            ->danger()
            ->send();
    }
}
