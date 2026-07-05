<?php

declare(strict_types=1);

use App\Enums\PortalType;
use App\Mail\PortalUserInvitationMail;
use App\Models\Company;
use App\Models\PortalInvitation;
use App\Models\Team;
use App\Models\User;

/**
 * The two portal invitation mails were consolidated into one class whose
 * subject and copy derive from the invitation's portal — these assertions
 * are what keeps a supplier invitee from receiving buyer-portal copy now
 * that the split classes no longer make that structurally impossible.
 */
function invitationForMail(PortalType $portal): PortalInvitation
{
    $team = Team::factory()->create();
    $admin = User::factory()->withPersonalTeam()->create();

    $company = $portal === PortalType::Supplier
        ? Company::factory()->supplier()->for($team)->create()
        : Company::factory()->buyer()->for($team)->create();

    return PortalInvitation::query()->create([
        'team_id' => $team->getKey(),
        'company_id' => $company->getKey(),
        'email' => 'invitee@example.test',
        'name' => 'Invitee',
        'portal' => $portal,
        'invited_by' => $admin->getKey(),
        'token' => PortalInvitation::generateToken(),
    ]);
}

it('uses buyer portal copy for customer invitations', function (): void {
    $mail = new PortalUserInvitationMail(invitationForMail(PortalType::Customer), 'https://example.test/accept');

    expect($mail->envelope()->subject)->toBe('Buyer Portal Access Invitation');

    $rendered = $mail->render();

    expect($rendered)->toContain('Buyer Portal Invitation')
        ->and($rendered)->toContain('buyer portal for')
        ->and($rendered)->toContain('submitting goods and services requests');
});

it('uses supplier portal copy for supplier invitations', function (): void {
    $mail = new PortalUserInvitationMail(invitationForMail(PortalType::Supplier), 'https://example.test/accept');

    expect($mail->envelope()->subject)->toBe('Supplier Portal Access Invitation');

    $rendered = $mail->render();

    expect($rendered)->toContain('Supplier Portal Invitation')
        ->and($rendered)->toContain('supplier portal for')
        ->and($rendered)->toContain('maintain your article prices');
});
