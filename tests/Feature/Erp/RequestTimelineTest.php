<?php

declare(strict_types=1);

use App\Enums\ActorType;
use App\Enums\RequestStage;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Livewire\RequestHistoryTimeline;
use App\Models\ActivityLog;
use App\Models\BuyerCreditLimitRequest;
use App\Models\BuyerCreditUsageHistory;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Concerns\LogsErpActivity;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Services\Timeline\RequestTimelineSource;
use App\Services\Timeline\TimelineAudience;
use App\Services\Timeline\TimelineParty;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\Relation;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team = $this->admin->personalTeam();

    actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team);

    $this->source = app(RequestTimelineSource::class);
});

/**
 * Seed a request + buyer quote + buyer order for the acting team, then clear
 * the activity log so each test only sees the rows it writes afterwards.
 *
 * @return array{request: Request, quote: BuyerQuote, order: BuyerOrder}
 */
function seedTimelineRequest(Tests\TestCase $test): array
{
    $request = Request::factory()->recycle($test->team)->create(['creator_id' => $test->admin->getKey()]);
    $quote = BuyerQuote::factory()->recycle($test->team)->for($request)->create([
        'buyer_id' => $request->buyer_id,
        'total' => '1200.0000',
    ]);
    $order = BuyerOrder::factory()->recycle($test->team)->for($request)->create([
        'buyer_id' => $request->buyer_id,
    ]);

    ActivityLog::query()->delete();

    return ['request' => $request, 'quote' => $quote, 'order' => $order];
}

it('merges quote edits, uploads, and credit ledger rows in reverse chronological order with attribution', function (): void {
    ['request' => $request, 'quote' => $quote, 'order' => $order] = seedTimelineRequest($this);

    $this->travelTo('2026-07-07 14:32:00');
    $quote->update(['total' => '950.0000']);

    $this->travelTo('2026-07-07 14:35:00');
    $request->addMediaFromString('dummy')
        ->usingFileName('PO-scan-signed.pdf')
        ->withCustomProperties([
            'uploader_id' => $this->admin->getKey(),
            'uploader_actor_type' => ActorType::Buyer->value,
        ])
        ->toMediaCollection('attachments');

    $this->travelTo('2026-07-07 14:40:00');
    BuyerCreditUsageHistory::query()->create([
        'team_id' => $this->team->getKey(),
        'buyer_id' => $request->buyer_id,
        'transaction_type' => 'debit',
        'amount' => '5000.00',
        'max_credit_limit_before' => 0,
        'max_credit_limit_after' => 0,
        'available_credit_before' => '20000.00',
        'available_credit_after' => '15000.00',
        'credit_used_before' => '0.00',
        'credit_used_after' => '5000.00',
        'related_type' => BuyerOrder::class,
        'related_id' => $order->getKey(),
        'description' => "Order {$order->order_number} confirmed",
        'created_by_id' => $this->admin->getKey(),
    ]);

    $this->travelBack();

    $entries = $this->source->entries($request, TimelineParty::staff())->items();

    expect($entries)->toHaveCount(3)
        ->and($entries[0]->lane)->toBe('credit')
        ->and($entries[0]->entryType)->toBe(TimelineAudience::ENTRY_CREDIT)
        ->and($entries[0]->headline)->toBe('Credit used 5,000.00 — available 20,000.00 → 15,000.00')
        ->and($entries[0]->subjectNumber)->toBe($order->order_number)
        ->and($entries[0]->actorLabel)->toBe($this->admin->name)
        ->and($entries[0]->properties['available_credit_before'])->toBe('20000.00')
        ->and($entries[0]->properties['available_credit_after'])->toBe('15000.00')
        ->and($entries[0]->properties['caused_by'])->toBe($order->order_number)
        ->and($entries[1]->entryType)->toBe(TimelineAudience::ENTRY_MEDIA)
        ->and($entries[1]->actorType)->toBe(ActorType::Buyer)
        ->and($entries[1]->actorLabel)->toBe($this->admin->name)
        ->and($entries[1]->headline)->toBe('uploaded PO-scan-signed.pdf → Attachments')
        ->and($entries[2]->entryType)->toBe(TimelineAudience::ENTRY_ACTIVITY)
        ->and($entries[2]->event)->toBe('updated')
        ->and($entries[2]->actorType)->toBe(ActorType::Staff)
        ->and($entries[2]->actorLabel)->toBe($this->admin->name)
        ->and($entries[2]->subjectNumber)->toBe($quote->quote_number)
        ->and($entries[2]->changedFieldCount)->toBe(1)
        ->and($entries[2]->properties['attributes'])->toMatchArray(['total' => '950.0000'])
        ->and($entries[2]->properties['old'])->toMatchArray(['total' => '1200.0000']);
});

it('renders unstamped media as System and approved limit changes with limit balances', function (): void {
    ['request' => $request] = seedTimelineRequest($this);

    $request->addMediaFromString('dummy')
        ->usingFileName('legacy-scan.pdf')
        ->toMediaCollection('attachments');

    BuyerCreditUsageHistory::query()->create([
        'team_id' => $this->team->getKey(),
        'buyer_id' => $request->buyer_id,
        'transaction_type' => 'approved',
        'amount' => '15000.00',
        'max_credit_limit_before' => '10000.00',
        'max_credit_limit_after' => '25000.00',
        'available_credit_before' => '10000.00',
        'available_credit_after' => '25000.00',
        'credit_used_before' => 0,
        'credit_used_after' => 0,
        'related_type' => BuyerCreditLimitRequest::class,
        'related_id' => 999,
        'description' => 'Credit limit increased from 10,000.00 to 25,000.00',
        'created_by_id' => null,
    ]);

    $entries = collect($this->source->entries($request, TimelineParty::staff())->items());

    $media = $entries->firstWhere('entryType', TimelineAudience::ENTRY_MEDIA);
    $limitChange = $entries->firstWhere('event', 'approved');

    expect($media)->not->toBeNull()
        ->and($media->actorType)->toBe(ActorType::System)
        ->and($media->actorLabel)->toBe('System')
        ->and($limitChange)->not->toBeNull()
        ->and($limitChange->lane)->toBe('credit')
        ->and($limitChange->headline)->toBe('Credit limit 10,000.00 → 25,000.00')
        ->and($limitChange->actorLabel)->toBe('System');
});

it('paginates newest-first and rejects portal parties', function (): void {
    ['request' => $request, 'quote' => $quote] = seedTimelineRequest($this);

    $this->travelTo('2026-07-05 09:00:00');
    $quote->update(['total' => '100.0000']);
    $this->travelTo('2026-07-06 09:00:00');
    $quote->update(['total' => '200.0000']);
    $this->travelTo('2026-07-07 09:00:00');
    $quote->update(['total' => '300.0000']);
    $this->travelBack();

    $pageOne = $this->source->entries($request, TimelineParty::staff(), page: 1, perPage: 2);
    $pageTwo = $this->source->entries($request, TimelineParty::staff(), page: 2, perPage: 2);

    expect($pageOne->total())->toBe(3)
        ->and($pageOne->lastPage())->toBe(2)
        ->and($pageOne->items())->toHaveCount(2)
        ->and($pageOne->items()[0]->properties['attributes']['total'])->toBe('300.0000')
        ->and($pageOne->items()[1]->properties['attributes']['total'])->toBe('200.0000')
        ->and($pageTwo->items())->toHaveCount(1)
        ->and($pageTwo->items()[0]->properties['attributes']['total'])->toBe('100.0000');

    expect(fn () => $this->source->entries($request, TimelineParty::buyer(1)))
        ->toThrow(InvalidArgumentException::class);
});

it('excludes activity on records outside the request tree and gates the detail modal accordingly', function (): void {
    ['request' => $request] = seedTimelineRequest($this);

    $otherRequest = Request::factory()->recycle($this->team)->create();
    $foreignQuote = BuyerQuote::factory()->recycle($this->team)->for($otherRequest)->create([
        'buyer_id' => $otherRequest->buyer_id,
    ]);

    ActivityLog::query()->delete();
    $foreignQuote->update(['total' => '999.0000']);

    $entries = $this->source->entries($request, TimelineParty::staff());
    $foreignActivity = ActivityLog::query()->latest('id')->firstOrFail();

    expect($entries->total())->toBe(0)
        ->and($this->source->allowsActivity($request, TimelineParty::staff(), $foreignActivity))->toBeFalse()
        ->and($this->source->allowsActivity($otherRequest, TimelineParty::staff(), $foreignActivity))->toBeTrue();
});

it('has a capture path and a subject enumeration for every staff allow-listed subject type', function (): void {
    $morphMap = Relation::morphMap();
    $request = Request::factory()->recycle($this->team)->create();
    $numbers = $this->source->subjectNumbers($request, TimelineParty::staff());

    foreach (TimelineAudience::INTERNAL_SUBJECT_TYPES as $alias) {
        $class = $morphMap[$alias] ?? null;

        expect($class)->not->toBeNull("'{$alias}' is in the internal timeline allow-list but has no morph map entry.");

        expect(in_array(LogsErpActivity::class, class_uses_recursive($class), true))->toBeTrue(
            "{$class} ('{$alias}') is in the internal timeline allow-list but has no capture path (LogsErpActivity)."
        );

        expect($numbers)->toHaveKey($alias);
    }
});

it('keeps every logged request-child model inside the staff allow-list so no branch is silently unlogged', function (): void {
    $morphMap = Relation::morphMap();

    /** @var array<class-string, string> $deferred class => reason it may stay out of the interim enumeration */
    $deferred = [
        RequestItem::class => 'line-item logging ships with add-line-item-activity-logging (design D7)',
    ];

    $requestChildModels = collect(glob(app_path('Models').'/*.php'))
        ->map(fn (string $path): string => 'App\\Models\\'.basename($path, '.php'))
        ->filter(fn (string $class): bool => class_exists($class)
            && is_subclass_of($class, Illuminate\Database\Eloquent\Model::class)
            && in_array('request_id', (new $class)->getFillable(), true))
        ->values();

    expect($requestChildModels)->not->toBeEmpty();

    foreach ($requestChildModels as $class) {
        if (array_key_exists($class, $deferred)) {
            continue;
        }

        expect(in_array(LogsErpActivity::class, class_uses_recursive($class), true))->toBeTrue(
            "{$class} is a request child but has no capture path (LogsErpActivity) — its timeline branch would render empty."
        );

        $alias = array_search($class, $morphMap, true);

        expect($alias)->not->toBeFalse("{$class} is a logged request child but has no morph alias.")
            ->and(TimelineAudience::INTERNAL_SUBJECT_TYPES)->toContain($alias);
    }
});

it('renders the Activities section with day-grouped entries on the request view page', function (): void {
    ['request' => $request, 'quote' => $quote] = seedTimelineRequest($this);

    $quote->update(['total' => '950.0000']);

    // Activities render in the sticky right sidebar.
    livewire(ViewRequest::class, ['record' => $request->getKey()])
        ->assertOk()
        ->assertSeeLivewire(\App\Livewire\RequestHistorySidebar::class);

    livewire(RequestHistoryTimeline::class, ['request' => $request])
        ->assertOk()
        ->assertSee('Today')
        ->assertSee('updated Buyer Quote '.$quote->quote_number)
        ->assertSee('1 field')
        ->assertSee('View details')
        ->assertDontSee('Line-level price changes appear after line-item logging lands');

    livewire(\App\Livewire\RequestHistorySidebar::class, ['request' => $request])
        ->assertOk()
        ->assertSee('Line-level price changes appear after line-item logging lands');
});

it('opens the shared detail modal for an in-tree activity and forbids a foreign one', function (): void {
    ['request' => $request, 'quote' => $quote] = seedTimelineRequest($this);

    $quote->update(['total' => '950.0000']);
    $ownActivity = ActivityLog::query()->latest('id')->firstOrFail();

    $otherRequest = Request::factory()->recycle($this->team)->create();
    $foreignQuote = BuyerQuote::factory()->recycle($this->team)->for($otherRequest)->create([
        'buyer_id' => $otherRequest->buyer_id,
    ]);
    $foreignQuote->update(['total' => '111.0000']);
    $foreignActivity = ActivityLog::query()->latest('id')->firstOrFail();

    livewire(RequestHistoryTimeline::class, ['request' => $request])
        ->mountAction('details', arguments: ['activity' => $ownActivity->getKey()])
        ->assertOk()
        ->assertActionMounted('details');

    livewire(RequestHistoryTimeline::class, ['request' => $request])
        ->mountAction('details', arguments: ['activity' => $foreignActivity->getKey()])
        ->assertForbidden();
});

it('paginates the timeline component and clamps out-of-range pages', function (): void {
    ['request' => $request, 'quote' => $quote] = seedTimelineRequest($this);

    foreach (range(1, 3) as $i) {
        $this->travelTo("2026-07-0{$i} 09:00:00");
        $quote->update(['total' => "{$i}00.0000"]);
    }
    $this->travelBack();

    livewire(RequestHistoryTimeline::class, ['request' => $request])
        ->set('perPage', 2)
        ->assertSee('Page 1 of 2')
        ->call('nextPage')
        ->assertSee('Page 2 of 2')
        ->call('nextPage')
        ->assertSee('Page 2 of 2')
        ->call('previousPage')
        ->assertSee('Page 1 of 2');
});

it('renders a stage change as atomic workflow progress', function (): void {
    ['request' => $request] = seedTimelineRequest($this);

    $request->update(['stage' => RequestStage::PREPARING_BUYER_QUOTE]);

    livewire(RequestHistoryTimeline::class, ['request' => $request])
        ->assertOk()
        ->assertSee('Progressed to '.RequestStage::PREPARING_BUYER_QUOTE->getLabel())
        ->assertDontSee('updated Request');
});

it('renders an approval atomically with the role, attributed to the approver', function (): void {
    ['request' => $request] = seedTimelineRequest($this);

    $supplier = Company::factory()->supplier()->recycle($this->team)->create();
    $order = SupplierOrder::factory()->recycle($this->team)->for($request)->create([
        'supplier_id' => $supplier->getKey(),
    ]);

    ActivityLog::query()->delete();

    $order->update(['approver_1_id' => $this->admin->getKey()]);

    livewire(RequestHistoryTimeline::class, ['request' => $request])
        ->assertOk()
        ->assertSee('Approved Supplier Order '.$order->po_number.' — Approver 1');
});
