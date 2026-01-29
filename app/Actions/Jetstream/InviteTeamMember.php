<?php

declare(strict_types=1);

namespace App\Actions\Jetstream;

use App\Enums\CentralPurchasingRole;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Events\InvitingTeamMember;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Mail\TeamInvitation;
use Laravel\Jetstream\Rules\Role;
use Laravel\Jetstream\TeamInvitation as TeamInvitationModel;

final readonly class InviteTeamMember implements InvitesTeamMembers
{
    /**
     * Invite a new team member to the given team.
     */
    public function invite(User $user, Team $team, string $email, ?string $role = null, ?string $centralPurchasingRole = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $this->validate($team, $email, $role, $centralPurchasingRole);

        InvitingTeamMember::dispatch($team, $email, $role);

        $invitation = $team->teamInvitations()->create([
            'email' => $email,
            'role' => $role,
            'central_purchasing_role' => $centralPurchasingRole,
        ]);

        /** @var TeamInvitationModel $invitation */
        Mail::to($email)->send(new TeamInvitation($invitation));
    }

    /**
     * Validate the invite member operation.
     */
    private function validate(Team $team, string $email, ?string $role, ?string $centralPurchasingRole): void
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
            'central_purchasing_role' => $centralPurchasingRole,
        ], $this->rules($team, $role), [
            'email.unique' => __('This user has already been invited to the team.'),
            'central_purchasing_role.required' => __('The Central Purchasing role is required when role is Central Purchasing.'),
        ])->after(
            $this->ensureUserIsNotAlreadyOnTeam($team, $email)
        )->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for inviting a team member.
     *
     * @return array<string, list<Unique|Role|string>>
     */
    private function rules(Team $team, ?string $role): array
    {
        $rules = [
            'email' => [
                'required', 'email',
                Rule::unique(Jetstream::teamInvitationModel())->where(function (Builder $query) use ($team): void {
                    $query->where('team_id', $team->id);
                }),
            ],
            'role' => ['required', 'string', new Role],
        ];

        // Require central_purchasing_role when role is central_purchasing
        if ($role === 'central_purchasing') {
            $rules['central_purchasing_role'] = [
                'required',
                'string',
                Rule::in(array_column(CentralPurchasingRole::cases(), 'value')),
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
