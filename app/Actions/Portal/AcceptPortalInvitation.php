<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Models\CompanyPortalUser;
use App\Models\PortalInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Accepts a portal invitation for either portal. Two entry points, one per
 * caller situation:
 *
 * - acceptAsNewUser: the invited email has no account yet. Creates the user
 *   (email pre-verified — the tokened link proves control of the inbox),
 *   grants the portal membership, marks the invitation accepted.
 * - acceptAsExistingUser: the invited email already has an account. The caller
 *   must pass the authenticated user, whose email must match the invitation;
 *   credentials are never touched — one person may hold portal access at many
 *   companies without a password reset.
 *
 * Both share the membership grant, which activates the Invited-state
 * placeholder row rather than creating a duplicate.
 */
final readonly class AcceptPortalInvitation
{
    public function acceptAsNewUser(PortalInvitation $invitation, string $name, string $password): User
    {
        if (User::query()->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An account already exists for this email. Please sign in to accept the invitation.'],
            ]);
        }

        return DB::transaction(function () use ($invitation, $name, $password): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            $this->grantMembership($invitation, $user);

            return $user;
        });
    }

    public function acceptAsExistingUser(PortalInvitation $invitation, User $user): User
    {
        if ($user->email !== $invitation->email) {
            throw ValidationException::withMessages([
                'email' => ['This invitation was issued for a different email address.'],
            ]);
        }

        return DB::transaction(function () use ($invitation, $user): User {
            $this->grantMembership($invitation, $user);

            return $user;
        });
    }

    private function grantMembership(PortalInvitation $invitation, User $user): void
    {
        $invitedRow = CompanyPortalUser::query()
            ->where('company_id', $invitation->company_id)
            ->where('portal', $invitation->portal)
            ->whereNull('user_id')
            ->where('invited_email', $invitation->email)
            ->first();

        if ($invitedRow !== null) {
            $invitedRow->update([
                'user_id' => $user->getKey(),
                'is_active' => true,
            ]);
        } else {
            // Invitations issued before the Invited state existed have no
            // membership row yet — create it on acceptance as before.
            CompanyPortalUser::query()->updateOrCreate(
                [
                    'company_id' => $invitation->company_id,
                    'user_id' => $user->getKey(),
                    'portal' => $invitation->portal,
                ],
                [
                    'team_id' => $invitation->team_id,
                    'invited_by' => $invitation->invited_by,
                    'is_active' => true,
                ],
            );
        }

        $invitation->markAccepted();
    }
}
