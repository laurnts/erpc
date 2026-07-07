<?php

declare(strict_types=1);

use App\Enums\PortalType;
use App\Filament\Resources\BuyerResource\Pages\ViewBuyer;
use App\Filament\Resources\BuyerResource\RelationManagers\PortalUsersRelationManager;
use App\Filament\Resources\SupplierResource\Pages\ViewSupplier;
use App\Http\Middleware\ApplyTenantScopes;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\PortalInvitation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->user->assignRole('superadmin');
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
});

function portalMembership(object $testCase, Company $company, PortalType $portal, array $attributes = []): CompanyPortalUser
{
    return CompanyPortalUser::query()->create(array_merge([
        'team_id' => $testCase->team->getKey(),
        'company_id' => $company->getKey(),
        'user_id' => null,
        'portal' => $portal,
        'invited_by' => $testCase->user->getKey(),
        'is_active' => false,
        'invited_name' => 'Invited Person',
        'invited_email' => 'invited@portal.test',
    ], $attributes));
}

it('lists all three lifecycle states in one table', function (): void {
    $member = User::factory()->create();

    $invited = portalMembership($this, $this->buyer, PortalType::Customer);
    $active = portalMembership($this, $this->buyer, PortalType::Customer, [
        'user_id' => $member->getKey(), 'is_active' => true, 'invited_email' => null, 'invited_name' => null,
    ]);
    $deactivated = portalMembership($this, $this->buyer, PortalType::Customer, [
        'user_id' => User::factory()->create()->getKey(), 'is_active' => false, 'invited_email' => null, 'invited_name' => null,
    ]);

    livewire(PortalUsersRelationManager::class, [
        'ownerRecord' => $this->buyer,
        'pageClass' => ViewBuyer::class,
    ])
        ->assertCanSeeTableRecords([$invited, $active, $deactivated])
        ->assertSee('invited@portal.test')
        ->assertSee('Invited Person')
        ->assertSee($member->name)
        ->assertSee($member->email);
});

it('revokes an invited membership together with its pending invitation', function (): void {
    $invited = portalMembership($this, $this->buyer, PortalType::Customer);

    $invitation = PortalInvitation::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $this->buyer->getKey(),
        'email' => 'invited@portal.test',
        'name' => 'Invited Person',
        'portal' => PortalType::Customer,
        'invited_by' => $this->user->getKey(),
        'token' => PortalInvitation::generateToken(),
    ]);

    livewire(PortalUsersRelationManager::class, [
        'ownerRecord' => $this->buyer,
        'pageClass' => ViewBuyer::class,
    ])->callTableAction('revoke', $invited);

    expect(CompanyPortalUser::query()->find($invited->getKey()))->toBeNull()
        ->and(PortalInvitation::query()->find($invitation->getKey()))->toBeNull();
});

it('resends the invitation for an invited membership', function (): void {
    Mail::fake();

    $invited = portalMembership($this, $this->buyer, PortalType::Customer);

    livewire(PortalUsersRelationManager::class, [
        'ownerRecord' => $this->buyer,
        'pageClass' => ViewBuyer::class,
    ])->callTableAction('resend', $invited);

    Mail::assertSent(App\Mail\PortalUserInvitationMail::class);

    expect(PortalInvitation::query()->where('email', 'invited@portal.test')->whereNull('accepted_at')->count())->toBe(1);
});

it('deactivates an active membership and reactivates a deactivated one', function (): void {
    $member = User::factory()->create();
    $active = portalMembership($this, $this->buyer, PortalType::Customer, [
        'user_id' => $member->getKey(), 'is_active' => true, 'invited_email' => null, 'invited_name' => null,
    ]);

    $component = livewire(PortalUsersRelationManager::class, [
        'ownerRecord' => $this->buyer,
        'pageClass' => ViewBuyer::class,
    ]);

    $component
        ->assertTableActionHidden('revoke', $active)
        ->assertTableActionHidden('resend', $active)
        ->callTableAction('deactivate', $active);

    expect($active->refresh()->is_active)->toBeFalse();

    $component->callTableAction('reactivate', $active);

    expect($active->refresh()->is_active)->toBeTrue();
});

it('shows linked user name and email for an approved registration membership', function (): void {
    $portalUser = User::factory()->unverified()->create([
        'name' => 'David disini',
        'email' => 'daviddisini@gmail.com',
    ]);

    $active = portalMembership($this, $this->buyer, PortalType::Customer, [
        'user_id' => $portalUser->getKey(),
        'is_active' => true,
        'invited_email' => null,
        'invited_name' => null,
    ]);

    livewire(PortalUsersRelationManager::class, [
        'ownerRecord' => $this->buyer,
        'pageClass' => ViewBuyer::class,
    ])
        ->assertCanSeeTableRecords([$active])
        ->assertSee('David disini')
        ->assertSee('daviddisini@gmail.com');
});

it('shows portal user details even when the linked user is not a staff team member', function (): void {
    $portalUser = User::factory()->unverified()->create([
        'name' => 'External Portal User',
        'email' => 'external.portal@test',
    ]);

    $active = portalMembership($this, $this->buyer, PortalType::Customer, [
        'user_id' => $portalUser->getKey(),
        'is_active' => true,
        'invited_email' => null,
        'invited_name' => null,
    ]);

    User::addGlobalScope(
        ApplyTenantScopes::TENANT_USER_SCOPE,
        fn (Builder $query) => $query
            ->whereHas('teams', fn (Builder $query) => $query->where('teams.id', $this->team->getKey()))
            ->orWhereHas('ownedTeams', fn (Builder $query) => $query->where('teams.id', $this->team->getKey()))
    );

    livewire(PortalUsersRelationManager::class, [
        'ownerRecord' => $this->buyer,
        'pageClass' => ViewBuyer::class,
    ])
        ->assertCanSeeTableRecords([$active])
        ->assertSee('External Portal User')
        ->assertSee('external.portal@test');
});

it('lists supplier portal memberships on the supplier view', function (): void {
    $supplier = Company::factory()->supplier()->for($this->team)->create();

    $supplierInvited = portalMembership($this, $supplier, PortalType::Supplier, [
        'invited_email' => 'contact@supplier.test',
    ]);
    $customerTyped = portalMembership($this, $supplier, PortalType::Customer, [
        'invited_email' => 'wrongportal@supplier.test',
    ]);

    livewire(PortalUsersRelationManager::class, [
        'ownerRecord' => $supplier,
        'pageClass' => ViewSupplier::class,
    ])
        ->assertCanSeeTableRecords([$supplierInvited])
        ->assertCanNotSeeTableRecords([$customerTyped]);
});
