<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActorType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves who performed a logged action and under which team.
 *
 * Spatie only auto-resolves the causer from the default (web) guard, which
 * would leave portal (buyer/supplier) and sysadmin actions unattributed.
 * This helper inspects the active Filament panel first (the authoritative
 * signal for which guard is in play) and falls back to scanning known guards
 * for non-panel contexts.
 */
final class ActivityLogContext
{
    /**
     * Guard name => the actor type it represents.
     *
     * @var array<string, ActorType>
     */
    private const GUARD_ACTORS = [
        'web' => ActorType::Staff,
        'buyer' => ActorType::Buyer,
        'supplier' => ActorType::Supplier,
        'sysadmin' => ActorType::Admin,
    ];

    /**
     * The guard the current actor is authenticated through, if any.
     */
    public static function activeGuard(): ?string
    {
        $panelGuard = Filament::getCurrentPanel()?->getAuthGuard();

        if ($panelGuard !== null && Auth::guard($panelGuard)->check()) {
            return $panelGuard;
        }

        foreach (array_keys(self::GUARD_ACTORS) as $guard) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }

    /**
     * The actor type for the current request (System when unauthenticated).
     */
    public static function currentActorType(): ActorType
    {
        $guard = self::activeGuard();

        return $guard !== null ? self::GUARD_ACTORS[$guard] : ActorType::System;
    }

    /**
     * The model that should be recorded as the activity causer.
     */
    public static function currentCauser(): ?Model
    {
        $guard = self::activeGuard();

        $user = $guard !== null ? Auth::guard($guard)->user() : null;

        return $user instanceof Model ? $user : null;
    }

    /**
     * The team that owns the activity, from the active tenant or the causer.
     */
    public static function currentTeamId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant !== null) {
            return (int) $tenant->getKey();
        }

        $causer = self::currentCauser();

        if ($causer instanceof User && $causer->currentTeam !== null) {
            return (int) $causer->currentTeam->getKey();
        }

        return null;
    }
}
