<?php

declare(strict_types=1);

use App\Enums\PortalMembershipState;
use App\Enums\PortalType;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    config([
        'app.customer_portal_enabled' => true,
        'app.supplier_portal_enabled' => true,
    ]);

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
});

function lifecycleMembership(object $testCase, array $attributes = []): CompanyPortalUser
{
    return CompanyPortalUser::query()->create(array_merge([
        'team_id' => $testCase->team->getKey(),
        'company_id' => $testCase->buyer->getKey(),
        'user_id' => null,
        'portal' => PortalType::Customer,
        'invited_by' => $testCase->admin->getKey(),
        'is_active' => false,
        'invited_name' => 'Invited Person',
        'invited_email' => 'invited@buyer.test',
    ], $attributes));
}

it('derives the three lifecycle states from the record', function (): void {
    $user = User::factory()->create();

    $invited = lifecycleMembership($this);
    $active = lifecycleMembership($this, ['user_id' => $user->getKey(), 'is_active' => true]);
    $deactivated = lifecycleMembership($this, [
        'user_id' => User::factory()->create()->getKey(),
        'is_active' => false,
    ]);

    expect($invited->state())->toBe(PortalMembershipState::Invited)
        ->and($active->state())->toBe(PortalMembershipState::Active)
        ->and($deactivated->state())->toBe(PortalMembershipState::Deactivated);
});

it('creates an Invited membership row at invite time', function (): void {
    Illuminate\Support\Facades\Mail::fake();

    $invitation = app(App\Actions\Portal\InvitePortalUser::class)->execute(
        team: $this->team,
        company: $this->buyer,
        portal: PortalType::Customer,
        email: 'fresh@buyer.test',
        name: 'Fresh Invitee',
        invitedBy: $this->admin,
    );

    $membership = CompanyPortalUser::query()
        ->where('company_id', $this->buyer->getKey())
        ->where('invited_email', 'fresh@buyer.test')
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->state())->toBe(PortalMembershipState::Invited)
        ->and($membership->invited_name)->toBe('Fresh Invitee')
        ->and($invitation->accepted_at)->toBeNull();
});

it('activates the same membership row on acceptance instead of creating a duplicate', function (): void {
    Illuminate\Support\Facades\Mail::fake();

    app(App\Actions\Portal\InvitePortalUser::class)->execute(
        team: $this->team,
        company: $this->buyer,
        portal: PortalType::Customer,
        email: 'fresh@buyer.test',
        name: 'Fresh Invitee',
        invitedBy: $this->admin,
    );

    $invitation = App\Models\PortalInvitation::query()->where('email', 'fresh@buyer.test')->firstOrFail();

    $user = app(App\Actions\Portal\AcceptPortalInvitation::class)->acceptAsNewUser($invitation, 'Fresh Invitee', 'secret-password');

    $memberships = CompanyPortalUser::query()
        ->where('company_id', $this->buyer->getKey())
        ->where('portal', PortalType::Customer)
        ->get();

    expect($memberships)->toHaveCount(1)
        ->and($memberships->first()->user_id)->toBe($user->getKey())
        ->and($memberships->first()->state())->toBe(PortalMembershipState::Active);
});

it('grants no panel access for an invited-state membership on either portal', function (): void {
    $user = User::factory()->create();
    $user->teams()->detach();

    $supplier = Company::factory()->supplier()->for($this->team)->create();

    lifecycleMembership($this, ['invited_email' => $user->email]);
    lifecycleMembership($this, [
        'company_id' => $supplier->getKey(),
        'portal' => PortalType::Supplier,
        'invited_email' => $user->email,
    ]);

    expect($user->canAccessPanel(Filament::getPanel('customer')))->toBeFalse()
        ->and($user->canAccessPanel(Filament::getPanel('supplier')))->toBeFalse()
        ->and($user->hasActiveCustomerPortalAccess())->toBeFalse()
        ->and($user->hasActiveSupplierPortalAccess())->toBeFalse();
});
