<?php

declare(strict_types=1);

namespace App\Actions\Jetstream;

use App\Enums\CentralPurchasingRole;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule as ValidationRule;
use Laravel\Jetstream\Contracts\AddsTeamMembers;
use Laravel\Jetstream\Events\AddingTeamMember;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Rules\Role;

final readonly class AddTeamMember implements AddsTeamMembers
{
    /**
     * Add a new team member to the given team.
     */
    public function add(User $user, Team $team, string $email, ?string $role = null, ?string $centralPurchasingRole = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $this->validate($team, $email, $role, $centralPurchasingRole);

        $newTeamMember = Jetstream::findUserByEmailOrFail($email);

        AddingTeamMember::dispatch($team, $newTeamMember);

        $pivotData = ['role' => $role];
        if ($role === 'central_purchasing' && $centralPurchasingRole) {
            $pivotData['central_purchasing_role'] = $centralPurchasingRole;
        }

        $team->users()->attach($newTeamMember, $pivotData);

        TeamMemberAdded::dispatch($team, $newTeamMember);
    }

    /**
     * Validate the add member operation.
     */
    private function validate(Team $team, string $email, ?string $role, ?string $centralPurchasingRole): void
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
            'central_purchasing_role' => $centralPurchasingRole,
        ], $this->rules($role), [
            'email.exists' => __('We were unable to find a registered user with this email address.'),
            'central_purchasing_role.required' => __('The Central Purchasing role is required when role is Central Purchasing.'),
        ])->after(
            $this->ensureUserIsNotAlreadyOnTeam($team, $email)
        )->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for adding a team member.
     *
     * @return array<string, array<int, string|Rule>>
     */
    private function rules(?string $role): array
    {
        $rules = [
            'email' => ['required', 'email', 'exists:users'],
            'role' => ['required', 'string', new Role],
        ];

        // Require central_purchasing_role when role is central_purchasing
        if ($role === 'central_purchasing') {
            $rules['central_purchasing_role'] = [
                'required',
                'string',
                ValidationRule::in(array_column(CentralPurchasingRole::cases(), 'value')),
            ];
        } else {
            $rules['central_purchasing_role'] = ['nullable'];
        }

        return $rules;
    }

    /**
     * Ensure that the user is not already on the team.
     */
    private function ensureUserIsNotAlreadyOnTeam(Team $team, string $email): Closure
    {
        return function ($validator) use ($team, $email): void { // @pest-ignore-type

            $validator->errors()->addIf(
                $team->hasUserWithEmail($email),
                'email',
                __('This user already belongs to the team.')
            );
        };
    }
}
