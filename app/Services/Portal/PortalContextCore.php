<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\PortalType;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Shared engine behind the per-panel portal context services. Each portal gets
 * its own thin context class (resolved by class name — Filament's static code
 * paths require distinct classes, not conditional bindings) delegating here.
 */
final readonly class PortalContextCore
{
    public function __construct(
        private string $guard,
        private PortalType $portal,
        private string $companyRoleColumn,
        private string $sessionPrefix,
    ) {}

    public function teamId(): int
    {
        $teamId = Session::get($this->sessionKey('team_id'));

        if (is_int($teamId)) {
            return $teamId;
        }

        return $this->requireDefaultMembership()->team_id;
    }

    public function companyId(): int
    {
        $companyId = Session::get($this->sessionKey('company_id'));

        if (is_int($companyId) && $this->userHasAccessToCompany($companyId)) {
            return $companyId;
        }

        return $this->requireDefaultMembership()->company_id;
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
        $user ??= $this->authenticatedUser();

        if ($user === null) {
            return collect();
        }

        return $this->membershipQuery($user)
            ->with(['company', 'team'])
            ->orderBy('company_id')
            ->get();
    }

    public function setCompany(int $companyId): void
    {
        $user = $this->authenticatedUser();

        if ($user === null || ! $this->userHasAccessToCompany($companyId)) {
            throw new \InvalidArgumentException('User does not have portal access to this company.');
        }

        $membership = $this->membershipQuery($user)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $this->setFromMembership($membership);
    }

    public function clear(): void
    {
        Session::forget([$this->sessionKey('team_id'), $this->sessionKey('company_id')]);
    }

    private function requireDefaultMembership(): CompanyPortalUser
    {
        $membership = $this->activeMemberships()->first();

        if ($membership === null) {
            throw new \RuntimeException(sprintf('No active %s portal access found.', $this->portal->value));
        }

        $this->setFromMembership($membership);

        return $membership;
    }

    private function setFromMembership(CompanyPortalUser $membership): void
    {
        Session::put($this->sessionKey('team_id'), $membership->team_id);
        Session::put($this->sessionKey('company_id'), $membership->company_id);
    }

    private function userHasAccessToCompany(int $companyId): bool
    {
        $user = $this->authenticatedUser();

        if ($user === null) {
            return false;
        }

        return $this->membershipQuery($user)
            ->where('company_id', $companyId)
            ->exists();
    }

    /**
     * @return Builder<CompanyPortalUser>
     */
    private function membershipQuery(User $user): Builder
    {
        return CompanyPortalUser::query()
            ->where('user_id', $user->getKey())
            ->where('portal', $this->portal)
            ->where('is_active', true)
            ->whereHas('company', fn (Builder $query) => $query->where($this->companyRoleColumn, true));
    }

    private function authenticatedUser(): ?User
    {
        $user = auth()->guard($this->guard)->user();

        return $user instanceof User ? $user : null;
    }

    private function sessionKey(string $key): string
    {
        return $this->sessionPrefix.'.'.$key;
    }
}
