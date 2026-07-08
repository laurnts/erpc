<?php

declare(strict_types=1);

use App\Enums\PortalType;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Team;
use App\Models\User;
use App\Services\Portal\BuyerPortalContext;
use App\Services\Portal\SupplierPortalContext;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    config([
        'app.buyer_portal_enabled' => true,
        'app.supplier_portal_enabled' => true,
    ]);

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team->users()->attach($this->admin, ['role' => 'admin']);
    $this->admin->switchTeam($this->team);
});

/**
 * Discovers portal resource page classes so every new portal resource is
 * covered automatically, mirroring the admin-panel sweep in
 * App/ResourcePagesSmokeTest.php. Only pages mountable without a record
 * (List*, Create*) are included.
 *
 * @return array<string, class-string>
 */
function portalSmokeResourcePages(string $panelDirectory): array
{
    $pages = [];

    foreach (glob(dirname(__DIR__, 3).'/app/Filament/'.$panelDirectory.'/Resources/*/Pages/*.php') ?: [] as $path) {
        $class = basename($path, '.php');

        if (! str_starts_with($class, 'List') && ! str_starts_with($class, 'Create')) {
            continue;
        }

        $resourceDir = basename(dirname($path, 2));
        $pages[$resourceDir.'\\'.$class] = 'App\\Filament\\'.$panelDirectory.'\\Resources\\'.$resourceDir.'\\Pages\\'.$class;
    }

    ksort($pages);

    return $pages;
}

function actAsPortalMember(object $testCase, PortalType $portal): void
{
    $isSupplier = $portal === PortalType::Supplier;

    $company = $isSupplier
        ? Company::factory()->supplier()->for($testCase->team)->create()
        : Company::factory()->buyer()->for($testCase->team)->create();

    $portalUser = User::factory()->create();
    $portalUser->teams()->detach();

    CompanyPortalUser::query()->create([
        'team_id' => $testCase->team->getKey(),
        'company_id' => $company->getKey(),
        'user_id' => $portalUser->getKey(),
        'portal' => $portal,
        'invited_by' => $testCase->admin->getKey(),
        'is_active' => true,
    ]);

    $guard = $isSupplier ? 'supplier' : 'buyer';
    $testCase->actingAs($portalUser, $guard);
    Filament::setCurrentPanel($guard);

    $context = $isSupplier ? SupplierPortalContext::class : BuyerPortalContext::class;
    app($context)->setCompany($company->getKey());
}

test('supplier portal page renders: :dataset', function (string $page): void {
    actAsPortalMember($this, PortalType::Supplier);

    livewire($page)->assertOk();
})->with(portalSmokeResourcePages('Supplier'));

test('buyer portal page renders: :dataset', function (string $page): void {
    actAsPortalMember($this, PortalType::Buyer);

    livewire($page)->assertOk();
})->with(portalSmokeResourcePages('Buyer'));

test('both portals render branding through the shared portal shell', function (): void {
    actAsPortalMember($this, PortalType::Buyer);
    $buyerLogo = Filament::getPanel('buyer')->getBrandLogo();

    expect($buyerLogo)->toBeInstanceOf(\Illuminate\View\View::class)
        ->and($buyerLogo->name())->toBe('filament.portal.brand-logo');

    auth('buyer')->logout();

    actAsPortalMember($this, PortalType::Supplier);
    $supplierLogo = Filament::getPanel('supplier')->getBrandLogo();

    expect($supplierLogo)->toBeInstanceOf(\Illuminate\View\View::class)
        ->and($supplierLogo->name())->toBe('filament.portal.brand-logo');
});
