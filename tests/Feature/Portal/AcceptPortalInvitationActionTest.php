<?php

declare(strict_types=1);

use App\Actions\Portal\AcceptPortalInvitation;
use App\Enums\PortalType;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\PortalInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
});

function makeInvitation(object $testCase, PortalType $portal, string $email): PortalInvitation
{
    $company = $portal === PortalType::Supplier
        ? Company::factory()->supplier()->for($testCase->team)->create()
        : Company::factory()->buyer()->for($testCase->team)->create();

    return PortalInvitation::query()->create([
        'team_id' => $testCase->team->getKey(),
        'company_id' => $company->getKey(),
        'email' => $email,
        'name' => 'Invited Person',
        'portal' => $portal,
        'invited_by' => $testCase->admin->getKey(),
        'token' => PortalInvitation::generateToken(),
    ]);
}

it('creates a verified user with a portal-typed membership and marks the invitation accepted', function (): void {
    $invitation = makeInvitation($this, PortalType::Customer, 'new@buyer.test');

    $user = app(AcceptPortalInvitation::class)->execute($invitation, 'New Person', 'secret-password');

    expect($user->email)->toBe('new@buyer.test')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('secret-password', (string) $user->password))->toBeTrue()
        ->and($invitation->fresh()?->accepted_at)->not->toBeNull();

    $membership = CompanyPortalUser::query()
        ->where('company_id', $invitation->company_id)
        ->where('user_id', $user->getKey())
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->portal)->toBe(PortalType::Customer)
        ->and($membership->is_active)->toBeTrue();
});

it('copies the supplier portal type from the invitation onto the membership', function (): void {
    $invitation = makeInvitation($this, PortalType::Supplier, 'new@supplier.test');

    $user = app(AcceptPortalInvitation::class)->execute($invitation, 'Supplier Person', 'secret-password');

    expect(
        CompanyPortalUser::query()
            ->where('user_id', $user->getKey())
            ->where('portal', PortalType::Supplier)
            ->exists()
    )->toBeTrue();
});

it('grants portal access to an existing user without modifying their credentials', function (): void {
    $existing = User::factory()->create([
        'email' => 'existing@buyer.test',
        'password' => Hash::make('original-password'),
    ]);

    $invitation = makeInvitation($this, PortalType::Customer, 'existing@buyer.test');

    $user = app(AcceptPortalInvitation::class)->execute($invitation, 'Renamed Person', 'attacker-password');

    expect($user->is($existing))->toBeTrue()
        ->and(Hash::check('original-password', (string) $user->fresh()?->password))->toBeTrue()
        ->and(Hash::check('attacker-password', (string) $user->fresh()?->password))->toBeFalse();
});
