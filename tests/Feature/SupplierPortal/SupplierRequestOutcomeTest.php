<?php

declare(strict_types=1);

use App\Actions\SupplierPortal\AnnounceSupplierRequestOutcomes;
use App\Enums\PortalType;
use App\Enums\QEStatus;
use App\Enums\SupplierQuoteStatus;
use App\Filament\Supplier\Resources\SupplierRequestResource\Pages\ListSupplierRequests;
use App\Filament\Supplier\Resources\SupplierRequestResource\Pages\ViewSupplierRequest;
use App\Livewire\SupplierQuoteComparison;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Currency;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SupplierQuoteOutcomeNotification;
use App\Services\Portal\SupplierPortalContext;
use App\Services\SupplierPortal\SupplierRequestStatusPresenter;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    config(['app.supplier_portal_enabled' => true]);

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->personalTeam();
    $this->actingAs($this->user);
    Filament::setTenant($this->team);

    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create(['name' => 'Confidential Buyer Ltd']);
    $this->currency = Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    $this->request = Request::factory()
        ->for($this->team)
        ->recycle($this->buyer)
        ->create(['creator_id' => $this->user->getKey()]);
    $this->item = RequestItem::factory()->recycle($this->request)->withQuantity(1)->create();

    $this->supplierA = Company::factory()->supplier()->recycle($this->team)->create(['name' => 'Alpha Supplies']);
    $this->supplierB = Company::factory()->supplier()->recycle($this->team)->create(['name' => 'Beta Trading']);

    $this->quoteA = makeAnnounceRoundQuote($this->team, $this->request, $this->supplierA, $this->currency, $this->item, 100.0);
    $this->quoteB = makeAnnounceRoundQuote($this->team, $this->request, $this->supplierB, $this->currency, $this->item, 987.65);

    $this->portalUserA = User::factory()->create(['email' => 'alpha@supplier.test']);
    $this->portalUserB = User::factory()->create(['email' => 'beta@supplier.test']);

    createSupplierPortalMembership($this->team, $this->supplierA, $this->portalUserA, $this->user);
    createSupplierPortalMembership($this->team, $this->supplierB, $this->portalUserB, $this->user);
});

/**
 * Build a sent supplier quote with one priced line for the given request item.
 * The item observer recalculates totals, transitioning PENDING → RECEIVED.
 */
function makeAnnounceRoundQuote(Team $team, Request $request, Company $supplier, Currency $currency, RequestItem $requestItem, float $price): SupplierQuote
{
    $quote = SupplierQuote::factory()
        ->recycle($team)
        ->forRequest($request)
        ->forSupplier($supplier)
        ->withCurrency($currency)
        ->sentToSupplier()
        ->create();

    SupplierQuoteItem::factory()
        ->forSupplierQuote($quote)
        ->forRequestItem($requestItem)
        ->withPricing(1, $price)
        ->create();

    return $quote->refresh();
}

function createSupplierPortalMembership(Team $team, Company $company, User $user, User $invitedBy): CompanyPortalUser
{
    return CompanyPortalUser::query()->create([
        'team_id' => $team->getKey(),
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'portal' => PortalType::Supplier,
        'invited_by' => $invitedBy->getKey(),
        'is_active' => true,
    ]);
}

describe('Pre-announcement churn stays internal', function (): void {
    it('fires no supplier-facing notification while selections are applied and re-applied', function (): void {
        Notification::fake();
        $presenter = app(SupplierRequestStatusPresenter::class);

        livewire(SupplierQuoteComparison::class, ['request' => $this->request])
            ->call('selectSingleSupplier', $this->quoteA->getKey())
            ->call('applySelections')
            ->call('clearSelections')
            ->call('selectSingleSupplier', $this->quoteB->getKey())
            ->call('applySelections');

        expect($this->quoteB->refresh()->status)->toBe(SupplierQuoteStatus::SELECTED)
            ->and($this->quoteA->refresh()->status)->toBe(SupplierQuoteStatus::RECEIVED)
            ->and($this->quoteA->outcomes_announced_at)->toBeNull()
            ->and($this->quoteB->outcomes_announced_at)->toBeNull()
            ->and($presenter->label($this->quoteA))->toBe('Submitted — under review')
            ->and($presenter->label($this->quoteB))->toBe('Submitted — under review');

        Notification::assertNotSentTo([$this->portalUserA, $this->portalUserB], SupplierQuoteOutcomeNotification::class);
    });

    it('fires no outcome notification when the staff "obtained" shortcut jumps a quote to selected', function (): void {
        Notification::fake();

        $supplierC = Company::factory()->supplier()->recycle($this->team)->create();
        $portalUserC = User::factory()->create(['email' => 'gamma@supplier.test']);
        createSupplierPortalMembership($this->team, $supplierC, $portalUserC, $this->user);

        $obtainedQuote = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($supplierC)
            ->withCurrency($this->currency)
            ->sentToSupplier()
            ->create(['obtained' => true]);

        SupplierQuoteItem::factory()
            ->forSupplierQuote($obtainedQuote)
            ->forRequestItem($this->item)
            ->withPricing(1, 50.0)
            ->create();

        expect($obtainedQuote->refresh()->status)->toBe(SupplierQuoteStatus::SELECTED)
            ->and($obtainedQuote->outcomes_announced_at)->toBeNull();

        Notification::assertNothingSent();
    });
});

describe('Announcing outcomes', function (): void {
    it('rejects zero-selection losers, stamps the round, and notifies each supplier portal user exactly once', function (): void {
        Notification::fake();

        livewire(SupplierQuoteComparison::class, ['request' => $this->request])
            ->call('selectSingleSupplier', $this->quoteA->getKey())
            ->call('applySelections');

        $result = app(AnnounceSupplierRequestOutcomes::class)->execute($this->request);

        expect($result)->toBe(['winners' => 1, 'losers' => 1])
            ->and($this->quoteA->refresh()->status)->toBe(SupplierQuoteStatus::SELECTED)
            ->and($this->quoteA->outcomes_announced_at)->not->toBeNull()
            ->and($this->quoteB->refresh()->status)->toBe(SupplierQuoteStatus::REJECTED)
            ->and($this->quoteB->outcomes_announced_at)->not->toBeNull();

        Notification::assertSentToTimes($this->portalUserA, SupplierQuoteOutcomeNotification::class, 1);
        Notification::assertSentToTimes($this->portalUserB, SupplierQuoteOutcomeNotification::class, 1);
        Notification::assertSentTo(
            $this->portalUserA,
            SupplierQuoteOutcomeNotification::class,
            fn (SupplierQuoteOutcomeNotification $notification): bool => $notification->won === true,
        );
        Notification::assertSentTo(
            $this->portalUserB,
            SupplierQuoteOutcomeNotification::class,
            fn (SupplierQuoteOutcomeNotification $notification): bool => $notification->won === false,
        );

        // Re-running is a no-op: the round is announced, nobody is notified twice.
        expect(app(AnnounceSupplierRequestOutcomes::class)->execute($this->request->refresh()))->toBeNull();
        Notification::assertSentToTimes($this->portalUserA, SupplierQuoteOutcomeNotification::class, 1);
        Notification::assertSentToTimes($this->portalUserB, SupplierQuoteOutcomeNotification::class, 1);
    });

    it('never notifies suppliers whose quote was internally entered and never sent', function (): void {
        Notification::fake();

        $unsentSupplier = Company::factory()->supplier()->recycle($this->team)->create();
        $unsentPortalUser = User::factory()->create(['email' => 'unsent@supplier.test']);
        createSupplierPortalMembership($this->team, $unsentSupplier, $unsentPortalUser, $this->user);

        $unsentQuote = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($unsentSupplier)
            ->withCurrency($this->currency)
            ->create();

        SupplierQuoteItem::factory()
            ->forSupplierQuote($unsentQuote)
            ->forRequestItem($this->item)
            ->withPricing(1, 300.0)
            ->create();

        livewire(SupplierQuoteComparison::class, ['request' => $this->request])
            ->call('selectSingleSupplier', $this->quoteA->getKey())
            ->call('applySelections');

        app(AnnounceSupplierRequestOutcomes::class)->execute($this->request);

        // Internally the loser is rejected like any other, but no supplier-facing
        // notification leaks a solicitation staff never issued.
        expect($unsentQuote->refresh()->status)->toBe(SupplierQuoteStatus::REJECTED)
            ->and($unsentQuote->outcomes_announced_at)->not->toBeNull();

        Notification::assertNotSentTo($unsentPortalUser, SupplierQuoteOutcomeNotification::class);
        Notification::assertSentToTimes($this->portalUserA, SupplierQuoteOutcomeNotification::class, 1);
    });

    it('keeps rejected losers visible in the comparison matrix and the QE snapshot', function (): void {
        Notification::fake();

        livewire(SupplierQuoteComparison::class, ['request' => $this->request])
            ->call('selectSingleSupplier', $this->quoteA->getKey())
            ->call('applySelections');

        $qe = QuotationEvaluation::factory()
            ->forRequest($this->request)
            ->create(['creator_id' => $this->user->getKey()]);
        $qe->syncSnapshotData();

        expect(collect($qe->refresh()->getSuppliers())->pluck('name')->all())
            ->toContain('Alpha Supplies', 'Beta Trading');

        app(AnnounceSupplierRequestOutcomes::class)->execute($this->request);

        // The snapshot was not touched by the announce transitions...
        expect(collect($qe->refresh()->getSuppliers())->pluck('name')->all())
            ->toContain('Alpha Supplies', 'Beta Trading');

        // ...and even an explicit later re-sync keeps the rejected loser (widened filter).
        $qe->syncSnapshotData();
        expect(collect($qe->refresh()->getSuppliers())->pluck('name')->all())
            ->toContain('Alpha Supplies', 'Beta Trading');

        // The comparison matrix still renders the rejected loser.
        livewire(SupplierQuoteComparison::class, ['request' => $this->request->refresh()])
            ->assertSee('Alpha Supplies')
            ->assertSee('Beta Trading');

        expect($this->quoteB->refresh()->status)->toBe(SupplierQuoteStatus::REJECTED);
    });

    it('does not reset an approved quotation evaluation', function (): void {
        Notification::fake();

        livewire(SupplierQuoteComparison::class, ['request' => $this->request])
            ->call('selectSingleSupplier', $this->quoteA->getKey())
            ->call('applySelections');

        $approvedAt = now()->subHour();
        $qe = QuotationEvaluation::factory()
            ->forRequest($this->request)
            ->create([
                'creator_id' => $this->user->getKey(),
                'status' => QEStatus::APPROVED,
                'dept_head_sales_approved_at' => $approvedAt,
                'deputy_director_approved_at' => $approvedAt,
                'director_approved_at' => $approvedAt,
            ]);

        app(AnnounceSupplierRequestOutcomes::class)->execute($this->request);

        $qe->refresh();

        expect($qe->status)->toBe(QEStatus::APPROVED)
            ->and($qe->dept_head_sales_approved_at)->not->toBeNull()
            ->and($qe->deputy_director_approved_at)->not->toBeNull()
            ->and($qe->director_approved_at)->not->toBeNull();
    });

    it('locks applySelections for the round after announcement', function (): void {
        Notification::fake();

        livewire(SupplierQuoteComparison::class, ['request' => $this->request])
            ->call('selectSingleSupplier', $this->quoteA->getKey())
            ->call('applySelections')
            ->call('announceOutcomes');

        expect($this->request->refresh()->supplierRequestOutcomesAnnounced())->toBeTrue()
            ->and($this->quoteB->refresh()->status)->toBe(SupplierQuoteStatus::REJECTED);

        // A later attempt to flip the selection to the announced loser refuses to run.
        livewire(SupplierQuoteComparison::class, ['request' => $this->request])
            ->call('selectSingleSupplier', $this->quoteB->getKey())
            ->call('applySelections')
            ->assertNotified('Outcomes already announced');

        expect($this->quoteA->refresh()->status)->toBe(SupplierQuoteStatus::SELECTED)
            ->and($this->quoteB->refresh()->status)->toBe(SupplierQuoteStatus::REJECTED)
            ->and(
                SupplierQuoteItem::query()
                    ->where('supplier_quote_id', $this->quoteA->getKey())
                    ->where('is_selected', true)
                    ->exists()
            )->toBeTrue()
            ->and(
                SupplierQuoteItem::query()
                    ->where('supplier_quote_id', $this->quoteB->getKey())
                    ->where('is_selected', true)
                    ->exists()
            )->toBeFalse();
    });
});

describe('Portal outcome visibility', function (): void {
    it('shows Won and Lost only after announcement, with the internal vocabulary never leaking', function (): void {
        Notification::fake();
        $presenter = app(SupplierRequestStatusPresenter::class);

        livewire(SupplierQuoteComparison::class, ['request' => $this->request])
            ->call('selectSingleSupplier', $this->quoteA->getKey())
            ->call('applySelections');

        // Pre-announcement: internal SELECTED/RECEIVED churn renders uniformly.
        expect($presenter->label($this->quoteA->refresh()))->toBe('Submitted — under review')
            ->and($presenter->label($this->quoteB->refresh()))->toBe('Submitted — under review');

        $this->actingAs($this->portalUserA, 'supplier');
        Filament::setCurrentPanel('supplier');
        app(SupplierPortalContext::class)->setCompany($this->supplierA->getKey());

        livewire(ListSupplierRequests::class)
            ->filterTable('status_group', 'won')
            ->assertCanNotSeeTableRecords([$this->quoteA]);

        app(AnnounceSupplierRequestOutcomes::class)->execute($this->request);

        expect($presenter->label($this->quoteA->refresh()))->toBe('Won')
            ->and($presenter->label($this->quoteB->refresh()))->toBe('Not selected');

        livewire(ListSupplierRequests::class)
            ->filterTable('status_group', 'won')
            ->assertCanSeeTableRecords([$this->quoteA])
            ->assertCanNotSeeTableRecords([$this->quoteB]);

        // The losing supplier sees their own quote under Lost — never the winner.
        $this->actingAs($this->portalUserB, 'supplier');
        app(SupplierPortalContext::class)->setCompany($this->supplierB->getKey());

        livewire(ListSupplierRequests::class)
            ->filterTable('status_group', 'lost')
            ->assertCanSeeTableRecords([$this->quoteB])
            ->assertCanNotSeeTableRecords([$this->quoteA])
            ->assertDontSee('Alpha Supplies');
    });

    it('shows item-level results on split awards using own selection flags only', function (): void {
        Notification::fake();

        $secondItem = RequestItem::factory()->recycle($this->request)->withQuantity(1)->create();

        $itemA2 = SupplierQuoteItem::factory()
            ->forSupplierQuote($this->quoteA)
            ->forRequestItem($secondItem)
            ->withPricing(1, 120.0)
            ->create();
        SupplierQuoteItem::factory()
            ->forSupplierQuote($this->quoteB)
            ->forRequestItem($secondItem)
            ->withPricing(1, 110.0)
            ->create();

        // Split award: item 1 to Alpha, item 2 to Beta.
        livewire(SupplierQuoteComparison::class, ['request' => $this->request])
            ->call('selectSupplierForItem', $this->item->getKey(), $this->quoteA->getKey())
            ->call('selectSupplierForItem', $secondItem->getKey(), $this->quoteB->getKey())
            ->call('applySelections');

        $result = app(AnnounceSupplierRequestOutcomes::class)->execute($this->request);

        expect($result)->toBe(['winners' => 2, 'losers' => 0])
            ->and($this->quoteA->refresh()->status)->toBe(SupplierQuoteStatus::SELECTED)
            ->and($this->quoteB->refresh()->status)->toBe(SupplierQuoteStatus::SELECTED);

        $this->actingAs($this->portalUserA, 'supplier');
        Filament::setCurrentPanel('supplier');
        app(SupplierPortalContext::class)->setCompany($this->supplierA->getKey());

        // Alpha sees per-item results from their own flags — and never the
        // winner's identity or the winning price on the lost item.
        livewire(ViewSupplierRequest::class, ['record' => $this->quoteA->getKey()])
            ->assertSee('Won')
            ->assertSee('Not selected')
            ->assertDontSee('Beta Trading')
            ->assertDontSee('110.00')
            ->assertDontSee('Confidential Buyer Ltd');

        expect($itemA2->refresh()->is_selected)->toBeFalse();

        // Both quotes land in Won: each supplier won at least one item.
        livewire(ListSupplierRequests::class)
            ->filterTable('status_group', 'won')
            ->assertCanSeeTableRecords([$this->quoteA]);
    });
});
