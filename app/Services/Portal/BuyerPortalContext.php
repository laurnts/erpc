<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\PortalType;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class BuyerPortalContext implements PortalContext
{
    private PortalContextCore $core;

    public function __construct()
    {
        $this->core = new PortalContextCore(
            guard: 'buyer',
            portal: PortalType::Buyer,
            companyRoleColumn: 'is_buyer',
            sessionPrefix: 'buyer_portal',
        );
    }

    public function teamId(): int
    {
        return $this->core->teamId();
    }

    public function companyId(): int
    {
        return $this->core->companyId();
    }

    public function company(): Company
    {
        return $this->core->company();
    }

    public function team(): Team
    {
        return $this->core->team();
    }

    /**
     * @return Collection<int, CompanyPortalUser>
     */
    public function activeMemberships(?User $user = null): Collection
    {
        return $this->core->activeMemberships($user);
    }

    public function setCompany(int $companyId): void
    {
        $this->core->setCompany($companyId);
    }

    public function clear(): void
    {
        $this->core->clear();
    }
}
