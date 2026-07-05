<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Enums\PortalType;
use App\Mail\PortalUserInvitationMail;
use App\Models\Company;
use App\Models\PortalInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Issues a portal invitation for either portal. The portal type drives the
 * company-role guard, the stale-invitation cleanup scope, the acceptance URL,
 * and the invitation mail copy.
 */
final readonly class InvitePortalUser
{
    public function execute(
        Team $team,
        Company $company,
        PortalType $portal,
        string $email,
        string $name,
        User $invitedBy,
    ): PortalInvitation {
        $this->assertCompanyHoldsPortalRole($company, $portal);

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['A user with this email address already has an account. Only new users can be invited to the portal.'],
            ]);
        }

        PortalInvitation::query()
            ->where('company_id', $company->getKey())
            ->where('email', $email)
            ->where('portal', $portal)
            ->whereNull('accepted_at')
            ->delete();

        $invitation = PortalInvitation::query()->create([
            'team_id' => $team->getKey(),
            'company_id' => $company->getKey(),
            'email' => $email,
            'name' => $name,
            'portal' => $portal,
            'invited_by' => $invitedBy->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        $invitation->load('company');

        $acceptPath = 'invitation/'.$invitation->token;
        $acceptUrl = $portal === PortalType::Supplier
            ? url()->getSupplierPortalUrl($acceptPath)
            : url()->getCustomerPortalUrl($acceptPath);

        Mail::to($email)->send(new PortalUserInvitationMail($invitation, $acceptUrl));

        return $invitation;
    }

    private function assertCompanyHoldsPortalRole(Company $company, PortalType $portal): void
    {
        if ($portal === PortalType::Supplier && ! $company->is_supplier) {
            throw ValidationException::withMessages([
                'email' => ['Supplier portal invitations can only be issued for supplier companies.'],
            ]);
        }

        if ($portal === PortalType::Customer && ! $company->is_buyer) {
            throw ValidationException::withMessages([
                'email' => ['Customer portal invitations can only be issued for buyer companies.'],
            ]);
        }
    }
}
