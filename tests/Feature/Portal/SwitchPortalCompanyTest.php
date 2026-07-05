<?php

declare(strict_types=1);

use App\Enums\PortalType;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Team;
use App\Models\User;
use App\Services\Portal\CustomerPortalContext;
use App\Services\Portal\SupplierPortalContext;
use Filament\Actions\Action;
use Filament\Facades\Filament;

beforeEach(function (): void {
    config([
        'app.customer_portal_enabled' => true,
        'app.supplier_portal_enabled' => true,
    ]);

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
});

function grantMembership(object $testCase, User $user, Company $company, PortalType $portal): void
{
    CompanyPortalUser::query()->create([
        'team_id' => $testCase->team->getKey(),
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'portal' => $portal,
        'invited_by' => $testCase->admin->getKey(),
        'is_active' => true,
    ]);
}

/**
 * Filament filters hidden actions out of getUserMenuItems(), so absence from
 * the menu IS the hidden state the visibility closure produces.
 */
function findSwitchCompanyAction(string $panelId): ?Action
{
    return collect(Filament::getPanel($panelId)->getUserMenuItems())
        ->first(fn (Action $item): bool => $item->getName() === 'switchPortalCompany');
}

it('hides the switch-company menu for a single-membership customer user', function (): void {
    $buyer = Company::factory()->buyer()->for($this->team)->create();
    $user = User::factory()->create();
    $user->teams()->detach();
    grantMembership($this, $user, $buyer, PortalType::Customer);

    $this->actingAs($user, 'customer');
    Filament::setCurrentPanel('customer');
    app(CustomerPortalContext::class)->setCompany($buyer->getKey());

    expect(findSwitchCompanyAction('customer'))->toBeNull();
});

it('shows the switch-company menu and switches the active company for a multi-membership customer user', function (): void {
    $first = Company::factory()->buyer()->for($this->team)->create();
    $second = Company::factory()->buyer()->for($this->team)->create();
    $user = User::factory()->create();
    $user->teams()->detach();
    grantMembership($this, $user, $first, PortalType::Customer);
    grantMembership($this, $user, $second, PortalType::Customer);

    $this->actingAs($user, 'customer');
    Filament::setCurrentPanel('customer');
    app(CustomerPortalContext::class)->setCompany($first->getKey());

    $action = findSwitchCompanyAction('customer');

    expect($action)->not->toBeNull();

    $action->call(['data' => ['company_id' => $second->getKey()]]);

    expect(app(CustomerPortalContext::class)->companyId())->toBe($second->getKey());
});

it('gates the supplier panel switch-company menu on supplier memberships', function (): void {
    $supplier = Company::factory()->supplier()->for($this->team)->create();
    $otherSupplier = Company::factory()->supplier()->for($this->team)->create();
    $user = User::factory()->create();
    $user->teams()->detach();
    grantMembership($this, $user, $supplier, PortalType::Supplier);

    $this->actingAs($user, 'supplier');
    Filament::setCurrentPanel('supplier');
    app(SupplierPortalContext::class)->setCompany($supplier->getKey());

    expect(findSwitchCompanyAction('supplier'))->toBeNull();

    grantMembership($this, $user, $otherSupplier, PortalType::Supplier);

    expect(findSwitchCompanyAction('supplier'))->not->toBeNull();
});
