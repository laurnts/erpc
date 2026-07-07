<?php

declare(strict_types=1);

use App\Auth\Notifications\ResetPassword;
use App\Enums\PortalType;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Team;
use App\Models\User;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Notification::fake();

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team->users()->attach($this->admin, ['role' => 'admin']);
    $this->admin->switchTeam($this->team);
});

it('sends a buyer portal password reset email to an active unverified portal user', function (): void {
    $buyer = Company::factory()->buyer()->for($this->team)->create();

    $portalUser = User::factory()->unverified()->create([
        'email' => 'buyer.reset@test',
    ]);

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $buyer->getKey(),
        'user_id' => $portalUser->getKey(),
        'portal' => PortalType::Customer,
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);

    Filament::setCurrentPanel('customer');

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => 'buyer.reset@test'])
        ->call('request')
        ->assertHasNoErrors();

    Notification::assertSentTo($portalUser, ResetPassword::class, function (ResetPassword $notification): bool {
        return str_contains($notification->url, '/buyer/password-reset/reset');
    });
});

it('sends a supplier portal password reset email to an active portal user', function (): void {
    $supplier = Company::factory()->supplier()->for($this->team)->create();

    $portalUser = User::factory()->create([
        'email' => 'supplier.reset@test',
    ]);

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $supplier->getKey(),
        'user_id' => $portalUser->getKey(),
        'portal' => PortalType::Supplier,
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);

    Filament::setCurrentPanel('supplier');

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => 'supplier.reset@test'])
        ->call('request')
        ->assertHasNoErrors();

    Notification::assertSentTo($portalUser, ResetPassword::class, function (ResetPassword $notification): bool {
        return str_contains($notification->url, '/supplier/password-reset/reset');
    });
});

it('sends a staff password reset email to an internal team user', function (): void {
    $staff = User::factory()->create([
        'email' => 'staff.reset@test',
    ]);
    $staff->teams()->attach($this->team, ['role' => 'admin']);

    Filament::setCurrentPanel('app');

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => 'staff.reset@test'])
        ->call('request')
        ->assertHasNoErrors();

    Notification::assertSentTo($staff, ResetPassword::class, function (ResetPassword $notification): bool {
        return ! str_contains($notification->url, '/buyer/')
            && ! str_contains($notification->url, '/supplier/');
    });
});

it('does not send a buyer portal reset email when the user lacks customer portal access', function (): void {
    $supplier = Company::factory()->supplier()->for($this->team)->create();

    $portalUser = User::factory()->create([
        'email' => 'supplier.only@test',
    ]);

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $supplier->getKey(),
        'user_id' => $portalUser->getKey(),
        'portal' => PortalType::Supplier,
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);

    Filament::setCurrentPanel('customer');

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => 'supplier.only@test'])
        ->call('request')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
});

it('exposes forgot-password on each panel login page', function (): void {
    config([
        'app.customer_portal_enabled' => true,
        'app.supplier_portal_enabled' => true,
    ]);

    $customerHost = App\Support\PanelDomain::customerHost();
    $supplierHost = App\Support\PanelDomain::supplierHost();
    $appHost = App\Support\PanelDomain::appHost();

    $this->get("http://{$customerHost}/buyer/login", ['Host' => $customerHost])
        ->assertOk()
        ->assertSee('Forgot password?', false);

    $this->get("http://{$supplierHost}/supplier/login", ['Host' => $supplierHost])
        ->assertOk()
        ->assertSee('Forgot password?', false);

    $this->get(url()->getAppUrl('login'), ['Host' => $appHost])
        ->assertOk()
        ->assertSee('Forgot password?', false);
});
