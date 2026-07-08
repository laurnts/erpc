<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Contract shared by the per-portal context facades (buyer, supplier) so
 * portal-agnostic plumbing — the shared panel shell, policies, widgets — can
 * type against one seam while each facade keeps its own guard, portal type,
 * company-role column, and session prefix.
 */
interface PortalContext
{
    public function teamId(): int;

    public function companyId(): int;

    public function company(): Company;

    public function team(): Team;

    /**
     * @return Collection<int, CompanyPortalUser>
     */
    public function activeMemberships(?User $user = null): Collection;

    public function setCompany(int $companyId): void;

    public function clear(): void;
}
