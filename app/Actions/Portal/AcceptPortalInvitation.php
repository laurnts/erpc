<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Models\CompanyPortalUser;
use App\Models\PortalInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Accepts a portal invitation for either portal: creates (or reuses) the user,
 * grants the portal-typed membership, and marks the invitation accepted — in
 * one transaction. Portal-agnostic by construction: everything derives from
 * the invitation, including its portal type.
 */
final readonly class AcceptPortalInvitation
{
    public function execute(PortalInvitation $invitation, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password): User {
            $user = User::query()->where('email', $invitation->email)->first();

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $invitation->email,
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ]);
            }
            // If a user with this email already exists (e.g. race condition), portal access
            // is granted below without modifying their existing credentials.

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

            $invitation->markAccepted();

            return $user;
        });
    }
}
