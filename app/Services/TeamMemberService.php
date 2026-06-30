<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CentralPurchasingRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class TeamMemberService
{
    /**
     * Get team members (Users) with a specific Central Purchasing role.
     *
     * @param  Team  $team  The team to query
     * @param  CentralPurchasingRole  $role  The Central Purchasing sub-role
     * @return Collection<int, User>
     */
    public static function getTeamMembersByCentralPurchasingRole(Team $team, CentralPurchasingRole $role): Collection
    {
        return User::query()
            ->whereHas('teams', function ($query) use ($team, $role) {
                $query->where('teams.id', $team->id)
                    ->where('team_user.role', 'central_purchasing')
                    ->where('team_user.central_purchasing_role', $role->value);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get finance role users who are marked as approvers.
     *
     * @param  Team  $team  The team to query
     * @return Collection<int, User>
     */
    public static function getFinanceApprovers(Team $team): Collection
    {
        return User::query()
            ->whereHas('teams', function ($query) use ($team) {
                $query->where('teams.id', $team->id)
                    ->where('team_user.role', 'central_purchasing')
                    ->where('team_user.central_purchasing_role', CentralPurchasingRole::FINANCE->value)
                    ->where('team_user.is_approver', true);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get team members (Users) with Central Purchasing role, optionally filtered by sub-role.
     *
     * @param  Team  $team  The team to query
     * @param  CentralPurchasingRole|null  $role  Optional sub-role filter
     * @return Collection<int, User>
     */
    public static function getCentralPurchasingTeamMembers(Team $team, ?CentralPurchasingRole $role = null): Collection
    {
        $query = User::query()
            ->whereHas('teams', function ($q) use ($team) {
                $q->where('teams.id', $team->id)
                    ->where('team_user.role', 'central_purchasing');
            });

        if ($role !== null) {
            $query->whereHas('teams', function ($q) use ($role) {
                $q->where('team_user.central_purchasing_role', $role->value);
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get team member options array for select fields.
     *
     * @param  Team  $team  The team to query
     * @param  CentralPurchasingRole  $role  The Central Purchasing sub-role
     * @return array<int, string> Array of [user_id => user_name]
     */
    public static function getTeamMemberOptionsByRole(Team $team, CentralPurchasingRole $role): array
    {
        return self::getTeamMembersByCentralPurchasingRole($team, $role)
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
            ->toArray();
    }

    /**
     * Get User ID from People ID for migration purposes.
     * Attempts to match by email (from custom fields) or name.
     *
     * @param  int  $peopleId  The People record ID
     * @return int|null The User ID if found, null otherwise
     */
    public static function getUserIdFromPeopleId(int $peopleId): ?int
    {
        $people = \App\Models\People::find($peopleId);

        if (! $people) {
            return null;
        }

        // Try to get email from custom fields
        $email = $people->getCustomFieldValue('email');

        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                return $user->id;
            }
        }

        // Fallback: try to match by name (less reliable)
        $user = User::where('name', $people->name)->first();

        return $user?->id;
    }
}
