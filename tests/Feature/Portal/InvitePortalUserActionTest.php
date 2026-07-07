<?php

declare(strict_types=1);

use App\Actions\Portal\InvitePortalUser;
use App\Enums\PortalMembershipState;
use App\Enums\PortalType;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\PortalInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Mail::fake();

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
});

it('invites an email that already has a user account', function (): void {
    User::factory()->create(['email' => 'existing@buyer.test']);

    $invitation = app(InvitePortalUser::class)->execute(
        team: $this->team,
        company: $this->buyer,
        portal: PortalType::Customer,
        email: 'existing@buyer.test',
        name: 'Existing Person',
        invitedBy: $this->admin,
    );

    $membership = CompanyPortalUser::query()
        ->where('company_id', $this->buyer->getKey())
        ->where('invited_email', 'existing@buyer.test')
        ->first();

    expect($invitation->email)->toBe('existing@buyer.test')
        ->and($membership)->not->toBeNull()
        ->and($membership->state())->toBe(PortalMembershipState::Invited);
});

it('sets a future expiry on the invitation', function (): void {
    $invitation = app(InvitePortalUser::class)->execute(
        team: $this->team,
        company: $this->buyer,
        portal: PortalType::Customer,
        email: 'fresh@buyer.test',
        name: 'Fresh Person',
        invitedBy: $this->admin,
    );

    expect($invitation->expires_at)->not->toBeNull()
        ->and($invitation->expires_at->isFuture())->toBeTrue()
        ->and($invitation->isExpired())->toBeFalse();
});

it('rejects inviting a user who already has active access to this company and portal', function (): void {
    $user = User::factory()->create(['email' => 'member@buyer.test']);

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $this->buyer->getKey(),
        'user_id' => $user->getKey(),
        'portal' => PortalType::Customer,
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);

    expect(fn () => app(InvitePortalUser::class)->execute(
        team: $this->team,
        company: $this->buyer,
        portal: PortalType::Customer,
        email: 'member@buyer.test',
        name: 'Member',
        invitedBy: $this->admin,
    ))->toThrow(ValidationException::class);
});

it('rejects inviting a user whose access to this company was deactivated', function (): void {
    $user = User::factory()->create(['email' => 'deactivated@buyer.test']);

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $this->buyer->getKey(),
        'user_id' => $user->getKey(),
        'portal' => PortalType::Customer,
        'invited_by' => $this->admin->getKey(),
        'is_active' => false,
    ]);

    expect(fn () => app(InvitePortalUser::class)->execute(
        team: $this->team,
        company: $this->buyer,
        portal: PortalType::Customer,
        email: 'deactivated@buyer.test',
        name: 'Deactivated',
        invitedBy: $this->admin,
    ))->toThrow(ValidationException::class);
});

it('allows inviting an existing member of one company to a different company', function (): void {
    $user = User::factory()->create(['email' => 'multi@buyer.test']);
    $otherBuyer = Company::factory()->buyer()->for($this->team)->create();

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $otherBuyer->getKey(),
        'user_id' => $user->getKey(),
        'portal' => PortalType::Customer,
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);

    $invitation = app(InvitePortalUser::class)->execute(
        team: $this->team,
        company: $this->buyer,
        portal: PortalType::Customer,
        email: 'multi@buyer.test',
        name: 'Multi Company',
        invitedBy: $this->admin,
    );

    expect($invitation->company_id)->toBe($this->buyer->getKey());

    expect(PortalInvitation::query()->where('email', 'multi@buyer.test')->count())->toBe(1);
});
