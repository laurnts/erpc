<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Enums\PortalType;
use App\Mail\PortalUserInvitationMail;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\PortalInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Issues a portal invitation for either portal. The portal type drives the
 * company-role guard, the stale-invitation cleanup scope, the acceptance URL,
 * and the invitation mail copy. Alongside the invitation (the token carrier),
 * an Invited-state membership row is created so the person is visible in the
 * Portal Users list from the moment of invitation.
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
            $portalLabel = $portal === PortalType::Supplier ? 'supplier portal' : 'customer portal';

            throw ValidationException::withMessages([
                'email' => ["A user with this email address already has an account. Only new users can be invited to the {$portalLabel}."],
            ]);
        }

        $invitation = DB::transaction(function () use ($team, $company, $portal, $email, $name, $invitedBy): PortalInvitation {
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

            CompanyPortalUser::query()->updateOrCreate(
                [
                    'company_id' => $company->getKey(),
                    'portal' => $portal,
                    'user_id' => null,
                    'invited_email' => $email,
                ],
                [
                    'team_id' => $team->getKey(),
                    'invited_by' => $invitedBy->getKey(),
                    'is_active' => false,
                    'invited_name' => $name,
                ],
            );

            return $invitation;
        });

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
