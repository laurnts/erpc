<?php

declare(strict_types=1);

namespace App\Actions\CustomerPortal;

use App\Mail\PortalUserInvitationMail;
use App\Models\Company;
use App\Models\PortalInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final readonly class InvitePortalUser
{
    public function execute(
        Team $team,
        Company $buyer,
        string $email,
        string $name,
        User $invitedBy,
    ): PortalInvitation {
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['A user with this email address already has an account. Only new users can be invited to the customer portal.'],
            ]);
        }

        PortalInvitation::query()
            ->where('company_id', $buyer->getKey())
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        $invitation = PortalInvitation::query()->create([
            'team_id' => $team->getKey(),
            'company_id' => $buyer->getKey(),
            'email' => $email,
            'name' => $name,
            'invited_by' => $invitedBy->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        $invitation->load('company');

        $acceptUrl = url()->getCustomerPortalUrl('invitation/'.$invitation->token);

        Mail::to($email)->send(new PortalUserInvitationMail($invitation, $acceptUrl));

        return $invitation;
    }
}
