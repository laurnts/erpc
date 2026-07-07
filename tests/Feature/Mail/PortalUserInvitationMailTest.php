<?php

declare(strict_types=1);

use App\Enums\PortalType;
use App\Mail\PortalUserInvitationMail;
use App\Models\Company;
use App\Models\PortalInvitation;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->admin = User::factory()->create();
    $this->buyer = Company::factory()->buyer()->for($this->team)->create(['name' => 'Acme Trading']);
});

function invitationFor(object $testCase, string $email): PortalInvitation
{
    return PortalInvitation::query()->create([
        'team_id' => $testCase->team->getKey(),
        'company_id' => $testCase->buyer->getKey(),
        'email' => $email,
        'name' => 'Invitee',
        'portal' => PortalType::Customer,
        'invited_by' => $testCase->admin->getKey(),
        'token' => PortalInvitation::generateToken(),
    ]);
}

it('tells a brand-new invitee to create an account', function (): void {
    $invitation = invitationFor($this, 'new@buyer.test');

    $mailable = new PortalUserInvitationMail($invitation->load('company'), 'https://accept.test');

    $mailable->assertSeeInHtml('create your account');
    $mailable->assertSeeInHtml('Accept Invitation');
});

it('tells an existing user to sign in to accept', function (): void {
    User::factory()->create(['email' => 'existing@buyer.test']);

    $invitation = invitationFor($this, 'existing@buyer.test');

    $mailable = new PortalUserInvitationMail($invitation->load('company'), 'https://accept.test');

    $mailable->assertSeeInHtml('Sign in to accept');
    $mailable->assertDontSeeInHtml('create your account');
});
