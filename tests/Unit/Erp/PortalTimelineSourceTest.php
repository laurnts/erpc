<?php

declare(strict_types=1);

use App\Data\TimelineEntry;
use App\Models\Company;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Services\Timeline\PortalTimelineSource;
use App\Services\Timeline\TimelineAudience;
use App\Services\Timeline\TimelineParty;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team = $this->admin->personalTeam();

    actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team);

    $this->source = app(PortalTimelineSource::class);
});

it('rejects internal parties', function (): void {
    $request = Request::factory()->recycle($this->team)->create();

    expect(fn () => $this->source->forParty($request, TimelineParty::staff()))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->source->forParty($request, TimelineParty::admin()))
        ->toThrow(InvalidArgumentException::class);
});

it('builds the buyer feed from allow-listed subject types only', function (): void {
    $request = Request::factory()->recycle($this->team)->create();

    \App\Models\BuyerQuote::factory()->recycle($this->team)->for($request)->sent()->create([
        'buyer_id' => $request->buyer_id,
    ]);

    // A supplier-side document on the same request must never surface for the buyer.
    SupplierQuote::factory()->recycle($this->team)->for($request)->create([
        'supplier_id' => Company::factory()->supplier()->recycle($this->team)->create()->getKey(),
    ]);

    $entries = $this->source->forParty($request, TimelineParty::buyer($request->buyer_id));

    $buyerAllowList = array_keys((new TimelineAudience)->subjectRules(TimelineParty::buyer($request->buyer_id)));

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        expect($entry)->toBeInstanceOf(TimelineEntry::class)
            ->and($buyerAllowList)->toContain($entry->subjectType);
    }

    expect(collect($entries)->pluck('subjectType')->unique()->all())->not->toContain('supplier_quote');
});

it('isolates one supplier from another supplier on a shared request', function (): void {
    $request = Request::factory()->recycle($this->team)->create();

    $supplierA = Company::factory()->supplier()->recycle($this->team)->create();
    $supplierB = Company::factory()->supplier()->recycle($this->team)->create();

    $quoteA = SupplierQuote::factory()->recycle($this->team)->for($request)->create([
        'supplier_id' => $supplierA->getKey(),
    ]);
    $quoteB = SupplierQuote::factory()->recycle($this->team)->for($request)->create([
        'supplier_id' => $supplierB->getKey(),
    ]);

    $entries = $this->source->forParty($request, TimelineParty::supplier($supplierA->getKey()));
    $subjectIds = collect($entries)
        ->where('subjectType', 'supplier_quote')
        ->pluck('subjectId')
        ->all();

    expect($subjectIds)->toContain($quoteA->getKey())
        ->and($subjectIds)->not->toContain($quoteB->getKey());
});
