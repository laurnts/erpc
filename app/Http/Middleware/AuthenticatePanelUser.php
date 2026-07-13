<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Panel-agnostic authentication: on every request, re-check canAccessPanel()
 * and force-logout users whose panel access has been revoked mid-session.
 */
final class AuthenticatePanelUser extends FilamentAuthenticate
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  array<string>  $guards
     */
    protected function authenticate(mixed $request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();

        $panel = Filament::getCurrentOrDefaultPanel();

        if ($user instanceof FilamentUser && ! $user->canAccessPanel($panel)) {
            $guard->logout();

            $this->unauthenticated($request, $guards);

            return;
        }

        parent::authenticate($request, $guards);
    }
}
