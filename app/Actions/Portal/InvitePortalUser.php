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
 *
 * An email that already has a user account may be invited to companies they
 * do not yet belong to — one person can hold portal access at several
 * companies. Acceptance requires them to sign in first (never a password
 * reset); the only block is re-inviting someone who already has a membership
 * row for this company+portal (reactivate that row instead).
 */
final readonly class InvitePortalUser
{
    private const int EXPIRY_DAYS = 7;

    public function execute(
        Team $team,
        Company $company,
        PortalType $portal,
        string $email,
        string $name,
        User $invitedBy,
    ): PortalInvitation {
        $this->assertCompanyHoldsPortalRole($company, $portal);

        $this->assertNoExistingMembership($company, $portal, $email);

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
                'expires_at' => now()->addDays(self::EXPIRY_DAYS),
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
            : url()->getBuyerPortalUrl($acceptPath);

        Mail::to($email)->send(new PortalUserInvitationMail($invitation, $acceptUrl));

        return $invitation;
    }

    /**
     * A person may belong to many companies, but only one membership row may
     * exist per company+portal (the DB enforces this). Re-inviting someone who
     * already has such a row — active or deactivated — would collide on
     * acceptance, so it is blocked here with guidance toward the Portal Users
     * list, where the row can be reactivated.
     */
    private function assertNoExistingMembership(Company $company, PortalType $portal, string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $membership = CompanyPortalUser::query()
            ->where('company_id', $company->getKey())
            ->where('portal', $portal)
            ->where('user_id', $user->getKey())
            ->first();

        if ($membership === null) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => [$membership->is_active
                ? 'This person already has access to this company. Manage them from the Portal Users list.'
                : 'This person had access that was deactivated. Reactivate them from the Portal Users list instead of re-inviting.'],
        ]);
    }

    private function assertCompanyHoldsPortalRole(Company $company, PortalType $portal): void
    {
        if ($portal === PortalType::Supplier && ! $company->is_supplier) {
            throw ValidationException::withMessages([
                'email' => ['Supplier portal invitations can only be issued for supplier companies.'],
            ]);
        }

        if ($portal === PortalType::Buyer && ! $company->is_buyer) {
            throw ValidationException::withMessages([
                'email' => ['Buyer portal invitations can only be issued for buyer companies.'],
            ]);
        }
    }
}
