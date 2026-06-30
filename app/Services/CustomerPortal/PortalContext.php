<?php

declare(strict_types=1);

namespace App\Services\CustomerPortal;

use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

final readonly class PortalContext
{
    private const SESSION_TEAM_KEY = 'customer_portal.team_id';

    private const SESSION_COMPANY_KEY = 'customer_portal.company_id';

    public function teamId(): int
    {
        $teamId = Session::get(self::SESSION_TEAM_KEY);

        if (is_int($teamId)) {
            return $teamId;
        }

        $membership = $this->resolveDefaultMembership();

        if ($membership === null) {
            throw new \RuntimeException('No active customer portal access found.');
        }

        $this->setFromMembership($membership);

        return $membership->team_id;
    }

    public function companyId(): int
    {
        $companyId = Session::get(self::SESSION_COMPANY_KEY);

        if (is_int($companyId) && $this->userHasAccessToCompany($companyId)) {
            return $companyId;
        }

        $membership = $this->resolveDefaultMembership();

        if ($membership === null) {
            throw new \RuntimeException('No active customer portal access found.');
        }

        $this->setFromMembership($membership);

        return $membership->company_id;
    }

    public function company(): Company
    {
        return Company::query()->findOrFail($this->companyId());
    }

    public function team(): Team
    {
        return Team::query()->findOrFail($this->teamId());
    }

    /**
     * @return Collection<int, CompanyPortalUser>
     */
    public function activeMemberships(?User $user = null): Collection
    {
        $user ??= auth()->guard('customer')->user() ?? auth()->user();

        if ($user === null) {
            return collect();
        }

        return CompanyPortalUser::query()
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->with(['company', 'team'])
            ->orderBy('company_id')
            ->get();
    }

    public function setCompany(int $companyId): void
    {
        if (! $this->userHasAccessToCompany($companyId)) {
            throw new \InvalidArgumentException('User does not have portal access to this company.');
        }

        $membership = CompanyPortalUser::query()
            ->where('user_id', auth()->guard('customer')->id() ?? auth()->id())
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->firstOrFail();

        $this->setFromMembership($membership);
    }

    public function clear(): void
    {
        Session::forget([self::SESSION_TEAM_KEY, self::SESSION_COMPANY_KEY]);
    }

    private function resolveDefaultMembership(): ?CompanyPortalUser
    {
        return $this->activeMemberships()->first();
    }

    private function setFromMembership(CompanyPortalUser $membership): void
    {
        Session::put(self::SESSION_TEAM_KEY, $membership->team_id);
        Session::put(self::SESSION_COMPANY_KEY, $membership->company_id);
    }

    private function userHasAccessToCompany(int $companyId): bool
    {
        $user = auth()->guard('customer')->user() ?? auth()->user();

        if ($user === null) {
            return false;
        }

        return CompanyPortalUser::query()
            ->where('user_id', $user->getKey())
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->exists();
    }
}
