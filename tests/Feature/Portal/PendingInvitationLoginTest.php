<?php

declare(strict_types=1);

use App\Actions\Portal\InvitePortalUser;
use App\Enums\PortalType;
use App\Filament\Buyer\Pages\Auth\BuyerLogin;
use App\Filament\Supplier\Pages\Auth\SupplierLogin;
use App\Models\Company;
use App\Models\PortalInvitation;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    config([
        'app.buyer_portal_enabled' => true,
        'app.supplier_portal_enabled' => true,
    ]);
    Mail::fake();

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team->users()->attach($this->admin, ['role' => 'admin']);
    $this->admin->switchTeam($this->team);

    $this->company = Company::factory()->buyerAndSupplier()->for($this->team)->create();

    $this->invitee = User::factory()->create([
        'email' => 'invited@dual.test',
    ]);
});

function invitePortalUser(PortalType $portal): PortalInvitation
{
    return app(InvitePortalUser::class)->execute(
        team: test()->team,
        company: test()->company,
        portal: $portal,
        email: 'invited@dual.test',
        name: 'Invited Person',
        invitedBy: test()->admin,
    );
}

it('lets an existing user with a pending supplier invitation sign in and lands them on the acceptance page', function (): void {
    $invitation = invitePortalUser(PortalType::Supplier);

    Filament::setCurrentPanel('supplier');

    livewire(SupplierLogin::class)
        ->fillForm([
            'email' => 'invited@dual.test',
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(url()->getSupplierPortalUrl('invitation/'.$invitation->token));

    $this->assertAuthenticatedAs($this->invitee, 'supplier');
});

it('lets an existing user with a pending buyer invitation sign in and lands them on the acceptance page', function (): void {
    $invitation = invitePortalUser(PortalType::Buyer);

    Filament::setCurrentPanel('buyer');

    livewire(BuyerLogin::class)
        ->fillForm([
            'email' => 'invited@dual.test',
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(url()->getBuyerPortalUrl('invitation/'.$invitation->token));

    $this->assertAuthenticatedAs($this->invitee, 'buyer');
});

it('still rejects a wrong password even with a pending invitation', function (): void {
    invitePortalUser(PortalType::Supplier);

    Filament::setCurrentPanel('supplier');

    livewire(SupplierLogin::class)
        ->fillForm([
            'email' => 'invited@dual.test',
            'password' => 'wrong-password',
        ])
        ->call('authenticate')
        ->assertHasErrors();

    $this->assertGuest('supplier');
});

it('still rejects a user with neither membership nor pending invitation', function (): void {
    Filament::setCurrentPanel('supplier');

    livewire(SupplierLogin::class)
        ->fillForm([
            'email' => 'invited@dual.test',
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasErrors();

    $this->assertGuest('supplier');
});

it('still rejects a user whose only invitation has expired', function (): void {
    $invitation = invitePortalUser(PortalType::Supplier);
    $invitation->update(['expires_at' => now()->subDay()]);

    Filament::setCurrentPanel('supplier');

    livewire(SupplierLogin::class)
        ->fillForm([
            'email' => 'invited@dual.test',
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasErrors();

    $this->assertGuest('supplier');
});

it('does not let a buyer-portal invitation unlock the supplier portal', function (): void {
    invitePortalUser(PortalType::Buyer);

    Filament::setCurrentPanel('supplier');

    livewire(SupplierLogin::class)
        ->fillForm([
            'email' => 'invited@dual.test',
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasErrors();

    $this->assertGuest('supplier');
});
