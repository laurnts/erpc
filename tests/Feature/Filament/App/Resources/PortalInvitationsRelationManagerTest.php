<?php

declare(strict_types=1);

use App\Enums\PortalType;
use App\Filament\Resources\BuyerResource\Pages\ViewBuyer;
use App\Filament\Resources\BuyerResource\RelationManagers\PortalInvitationsRelationManager;
use App\Models\Company;
use App\Models\PortalInvitation;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->user->assignRole('superadmin');
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
});

function buyerInvitation(object $testCase, array $attributes = []): PortalInvitation
{
    return PortalInvitation::query()->create(array_merge([
        'team_id' => $testCase->team->getKey(),
        'company_id' => $testCase->buyer->getKey(),
        'email' => 'pending@buyer.test',
        'name' => 'Pending Invitee',
        'portal' => PortalType::Customer,
        'invited_by' => $testCase->user->getKey(),
        'token' => PortalInvitation::generateToken(),
    ], $attributes));
}

it('lists pending invitations for the buyer', function (): void {
    $pending = buyerInvitation($this);

    livewire(PortalInvitationsRelationManager::class, [
        'ownerRecord' => $this->buyer,
        'pageClass' => ViewBuyer::class,
    ])
        ->assertCanSeeTableRecords([$pending])
        ->assertSee('pending@buyer.test');
});

it('marks accepted invitations and allows revoking only pending ones', function (): void {
    $pending = buyerInvitation($this);
    $accepted = buyerInvitation($this, [
        'email' => 'done@buyer.test',
        'accepted_at' => now(),
    ]);

    $component = livewire(PortalInvitationsRelationManager::class, [
        'ownerRecord' => $this->buyer,
        'pageClass' => ViewBuyer::class,
    ]);

    $component
        ->assertCanSeeTableRecords([$pending, $accepted])
        ->assertTableActionVisible('revoke', $pending)
        ->assertTableActionHidden('revoke', $accepted)
        ->callTableAction('revoke', $pending);

    expect(PortalInvitation::query()->find($pending->getKey()))->toBeNull()
        ->and(PortalInvitation::query()->find($accepted->getKey()))->not->toBeNull();
});

it('does not list supplier-typed invitations on the buyer view', function (): void {
    $supplierTyped = buyerInvitation($this, [
        'email' => 'supplier@dual.test',
        'portal' => PortalType::Supplier,
    ]);

    livewire(PortalInvitationsRelationManager::class, [
        'ownerRecord' => $this->buyer,
        'pageClass' => ViewBuyer::class,
    ])->assertCanNotSeeTableRecords([$supplierTyped]);
});
