<?php

declare(strict_types=1);

namespace App\Actions\SupplierPortal;

use App\Enums\PortalType;
use App\Mail\SupplierPortalUserInvitationMail;
use App\Models\Company;
use App\Models\PortalInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final readonly class InviteSupplierPortalUser
{
    public function execute(
        Team $team,
        Company $supplier,
        string $email,
        string $name,
        User $invitedBy,
    ): PortalInvitation {
        if (! $supplier->is_supplier) {
            throw ValidationException::withMessages([
                'email' => ['Supplier portal invitations can only be issued for supplier companies.'],
            ]);
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['A user with this email address already has an account. Only new users can be invited to the supplier portal.'],
            ]);
        }

        PortalInvitation::query()
            ->where('company_id', $supplier->getKey())
            ->where('email', $email)
            ->where('portal', PortalType::Supplier)
            ->whereNull('accepted_at')
            ->delete();

        $invitation = PortalInvitation::query()->create([
            'team_id' => $team->getKey(),
            'company_id' => $supplier->getKey(),
            'email' => $email,
            'name' => $name,
            'portal' => PortalType::Supplier,
            'invited_by' => $invitedBy->getKey(),
            'token' => PortalInvitation::generateToken(),
        ]);

        $invitation->load('company');

        $acceptUrl = url()->getSupplierPortalUrl('invitation/'.$invitation->token);

        Mail::to($email)->send(new SupplierPortalUserInvitationMail($invitation, $acceptUrl));

        return $invitation;
    }
}
