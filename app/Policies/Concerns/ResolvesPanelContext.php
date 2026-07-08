<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;
use Filament\Facades\Filament;

/**
 * Panel discrimination for panel-branched policies. The auth guard is the
 * primary signal (guards are 1:1 with portals and work outside Filament page
 * lifecycles); the current Filament panel id is the fallback.
 */
trait ResolvesPanelContext
{
    private function isBuyerPanel(): bool
    {
        if (auth()->guard('buyer')->check()) {
            return true;
        }

        return Filament::getCurrentPanel()?->getId() === 'buyer';
    }

    private function isSupplierPanel(): bool
    {
        if (config()->has('auth.guards.supplier') && auth()->guard('supplier')->check()) {
            return true;
        }

        return Filament::getCurrentPanel()?->getId() === 'supplier';
    }

    private function userOwnsBuyerCompany(User $user, ?int $buyerCompanyId): bool
    {
        return $buyerCompanyId !== null
            && in_array($buyerCompanyId, $user->activeBuyerPortalCompanyIds(), true);
    }

    private function userOwnsSupplierCompany(User $user, ?int $supplierCompanyId): bool
    {
        return $supplierCompanyId !== null
            && in_array($supplierCompanyId, $user->activeSupplierPortalCompanyIds(), true);
    }
}
