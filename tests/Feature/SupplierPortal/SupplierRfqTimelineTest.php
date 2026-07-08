<?php

declare(strict_types=1);

use App\Enums\PortalType;
use App\Filament\Supplier\Resources\SupplierRfqResource\Pages\ViewSupplierRfq;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;
use App\Services\Portal\SupplierPortalContext;
use App\Services\Timeline\PortalTimelineSource;
use App\Services\Timeline\TimelineParty;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    config(['app.supplier_portal_enabled' => true]);

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team->users()->attach($this->admin, ['role' => 'admin']);
    $this->admin->switchTeam($this->team);

    $this->supplier = Company::factory()->supplier()->for($this->team)->create([
        'name' => 'Own Supplier Co',
    ]);

    $this->portalUser = User::factory()->create(['email' => 'timeline@supplier.test']);

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $this->supplier->getKey(),
        'user_id' => $this->portalUser->getKey(),
        'portal' => PortalType::Supplier,
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);

    $this->usd = Currency::factory()->usd()->create();

    $this->buyer = Company::factory()->buyer()->for($this->team)->create(['name' => 'Confidential Buyer Ltd']);

    $this->request = Request::factory()->recycle($this->team)->create([
        'buyer_id' => $this->buyer->getKey(),
        'creator_id' => $this->admin->getKey(),
    ]);

    $this->quote = SupplierQuote::factory()
        ->recycle($this->team)
        ->forRequest($this->request)
        ->forSupplier($this->supplier)
        ->withCurrency($this->usd)
        ->pending()
        ->sentToSupplier()
        ->validFor(30)
        ->create(['creator_id' => $this->admin->getKey()]);

    $this->actingAs($this->portalUser, 'supplier');
    Filament::setCurrentPanel('supplier');
    app(SupplierPortalContext::class)->setCompany($this->supplier->getKey());
});

it('renders the activity section with the supplier\'s own quotation activity', function (): void {
    livewire(ViewSupplierRfq::class, ['record' => $this->quote->getKey()])
        ->assertSee('Activities')
        ->assertSee($this->quote->quote_number)
        ->assertDontSee('No activity recorded for this request yet.');

    $entries = app(PortalTimelineSource::class)
        ->forParty($this->request, TimelineParty::supplier($this->supplier->getKey()));

    $subjectIds = collect($entries)
        ->where('subjectType', 'supplier_quote')
        ->pluck('subjectId')
        ->all();

    expect($subjectIds)->toContain($this->quote->getKey());
});

it('never surfaces another supplier\'s activity on a shared request', function (): void {
    $rival = Company::factory()->supplier()->for($this->team)->create(['name' => 'Rival Supplier Co']);

    $rivalQuote = SupplierQuote::factory()
        ->recycle($this->team)
        ->forRequest($this->request)
        ->forSupplier($rival)
        ->withCurrency($this->usd)
        ->pending()
        ->sentToSupplier()
        ->validFor(30)
        ->create(['creator_id' => $this->admin->getKey()]);

    livewire(ViewSupplierRfq::class, ['record' => $this->quote->getKey()])
        ->assertSee('Activities')
        ->assertDontSee($rivalQuote->quote_number)
        ->assertDontSee('Rival Supplier Co');

    $entries = app(PortalTimelineSource::class)
        ->forParty($this->request, TimelineParty::supplier($this->supplier->getKey()));

    $subjectIds = collect($entries)
        ->where('subjectType', 'supplier_quote')
        ->pluck('subjectId')
        ->all();

    expect($subjectIds)->toContain($this->quote->getKey())
        ->and($subjectIds)->not->toContain($rivalQuote->getKey());
});
