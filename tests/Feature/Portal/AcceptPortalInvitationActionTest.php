<?php

declare(strict_types=1);

use App\Actions\Portal\AcceptPortalInvitation;
use App\Enums\PortalMembershipState;
use App\Enums\PortalType;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\PortalInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
});

function makeInvitation(object $testCase, PortalType $portal, string $email): PortalInvitation
{
    $company = $portal === PortalType::Supplier
        ? Company::factory()->supplier()->for($testCase->team)->create()
        : Company::factory()->buyer()->for($testCase->team)->create();

    $invitation = PortalInvitation::query()->create([
        'team_id' => $testCase->team->getKey(),
        'company_id' => $company->getKey(),
        'email' => $email,
        'name' => 'Invited Person',
        'portal' => $portal,
        'invited_by' => $testCase->admin->getKey(),
        'token' => PortalInvitation::generateToken(),
    ]);

    CompanyPortalUser::query()->create([
        'team_id' => $testCase->team->getKey(),
        'company_id' => $company->getKey(),
        'user_id' => null,
        'portal' => $portal,
        'invited_by' => $testCase->admin->getKey(),
        'is_active' => false,
        'invited_name' => 'Invited Person',
        'invited_email' => $email,
    ]);

    return $invitation;
}

describe('acceptAsNewUser', function (): void {
    it('creates a verified user with a portal-typed membership and marks the invitation accepted', function (): void {
        $invitation = makeInvitation($this, PortalType::Buyer, 'new@buyer.test');

        $user = app(AcceptPortalInvitation::class)->acceptAsNewUser($invitation, 'New Person', 'secret-password');

        expect($user->email)->toBe('new@buyer.test')
            ->and($user->hasVerifiedEmail())->toBeTrue()
            ->and(Hash::check('secret-password', (string) $user->password))->toBeTrue()
            ->and($invitation->fresh()?->accepted_at)->not->toBeNull();

        $membership = CompanyPortalUser::query()
            ->where('company_id', $invitation->company_id)
            ->where('user_id', $user->getKey())
            ->first();

        expect($membership)->not->toBeNull()
            ->and($membership->portal)->toBe(PortalType::Buyer)
            ->and($membership->is_active)->toBeTrue();
    });

    it('copies the supplier portal type from the invitation onto the membership', function (): void {
        $invitation = makeInvitation($this, PortalType::Supplier, 'new@supplier.test');

        $user = app(AcceptPortalInvitation::class)->acceptAsNewUser($invitation, 'Supplier Person', 'secret-password');

        expect(
            CompanyPortalUser::query()
                ->where('user_id', $user->getKey())
                ->where('portal', PortalType::Supplier)
                ->exists()
        )->toBeTrue();
    });

    it('refuses to create a second account when the email already has one', function (): void {
        User::factory()->create(['email' => 'existing@buyer.test']);

        $invitation = makeInvitation($this, PortalType::Buyer, 'existing@buyer.test');

        expect(fn () => app(AcceptPortalInvitation::class)->acceptAsNewUser($invitation, 'Existing', 'secret-password'))
            ->toThrow(ValidationException::class);
    });
});

describe('acceptAsExistingUser', function (): void {
    it('grants portal access to an existing user without modifying their credentials', function (): void {
        $existing = User::factory()->create([
            'email' => 'existing@buyer.test',
            'password' => Hash::make('original-password'),
        ]);

        $invitation = makeInvitation($this, PortalType::Buyer, 'existing@buyer.test');

        $user = app(AcceptPortalInvitation::class)->acceptAsExistingUser($invitation, $existing);

        expect($user->is($existing))->toBeTrue()
            ->and(Hash::check('original-password', (string) $user->fresh()?->password))->toBeTrue()
            ->and($invitation->fresh()?->accepted_at)->not->toBeNull();

        $membership = CompanyPortalUser::query()
            ->where('company_id', $invitation->company_id)
            ->where('user_id', $existing->getKey())
            ->first();

        expect($membership)->not->toBeNull()
            ->and($membership->state())->toBe(PortalMembershipState::Active);
    });

    it('activates the invited placeholder row instead of creating a duplicate', function (): void {
        $existing = User::factory()->create(['email' => 'existing@buyer.test']);

        $invitation = makeInvitation($this, PortalType::Buyer, 'existing@buyer.test');

        app(AcceptPortalInvitation::class)->acceptAsExistingUser($invitation, $existing);

        $memberships = CompanyPortalUser::query()
            ->where('company_id', $invitation->company_id)
            ->where('portal', PortalType::Buyer)
            ->get();

        expect($memberships)->toHaveCount(1)
            ->and($memberships->first()->user_id)->toBe($existing->getKey());
    });

    it('refuses to grant access to a user whose email does not match the invitation', function (): void {
        $other = User::factory()->create(['email' => 'someone-else@buyer.test']);

        $invitation = makeInvitation($this, PortalType::Buyer, 'invited@buyer.test');

        expect(fn () => app(AcceptPortalInvitation::class)->acceptAsExistingUser($invitation, $other))
            ->toThrow(ValidationException::class);

        expect($invitation->fresh()?->accepted_at)->toBeNull();
    });
});
