<?php

declare(strict_types=1);

use App\Enums\RequestStage;
use App\Enums\RequestSubmissionMethod;
use App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ViewBuyerRequest;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;
use App\Services\BuyerPortal\BuyerRequestStagePresenter;
use App\Services\Portal\BuyerPortalContext;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Notification::fake();
    config(['app.buyer_portal_enabled' => true]);

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team->users()->attach($this->admin, ['role' => 'admin']);
    $this->admin->switchTeam($this->team);

    $this->buyer = Company::factory()->buyer()->for($this->team)->create();

    $this->portalUser = User::factory()->create([
        'email' => 'activity.portal@buyer.test',
    ]);

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $this->buyer->getKey(),
        'user_id' => $this->portalUser->getKey(),
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);
});

it('renders the buyer activity section on the request detail page', function (): void {
    $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
        'submission_method' => RequestSubmissionMethod::MANUAL,
        'submitted_at' => now(),
        'stage' => RequestStage::DRAFT,
    ]);

    BuyerQuote::factory()
        ->for($this->team)
        ->for($request)
        ->for($this->buyer, 'buyer')
        ->sent()
        ->withTotals(1000, 100, 1100)
        ->create();

    $request->update(['stage' => RequestStage::PREPARING_BUYER_QUOTE]);

    $this->actingAs($this->portalUser, 'buyer');
    Filament::setCurrentPanel('buyer');
    app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

    $stageLabel = app(BuyerRequestStagePresenter::class)
        ->labelForStage(RequestStage::PREPARING_BUYER_QUOTE);

    livewire(ViewBuyerRequest::class, ['record' => $request->getRouteKey()])
        ->assertOk()
        ->assertSee('Activity')
        ->assertSee($stageLabel);
});

it('does not surface supplier activity to the buyer on the request detail page', function (): void {
    $supplier = Company::factory()->supplier()->for($this->team)->create();

    $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
        'submission_method' => RequestSubmissionMethod::MANUAL,
        'submitted_at' => now(),
        'stage' => RequestStage::DRAFT,
    ]);

    $supplierQuote = SupplierQuote::factory()
        ->for($this->team)
        ->for($request)
        ->create([
            'supplier_id' => $supplier->getKey(),
        ]);

    $request->update(['stage' => RequestStage::PREPARING_BUYER_QUOTE]);

    $this->actingAs($this->portalUser, 'buyer');
    Filament::setCurrentPanel('buyer');
    app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());

    livewire(ViewBuyerRequest::class, ['record' => $request->getRouteKey()])
        ->assertOk()
        ->assertSee('Activity')
        ->assertDontSee($supplierQuote->quote_number);
});
