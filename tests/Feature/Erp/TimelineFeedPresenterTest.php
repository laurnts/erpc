<?php

declare(strict_types=1);

use App\Data\TimelineEntry;
use App\Enums\ActorType;
use App\Enums\TimelineHistoryFilter;
use App\Models\Request;
use App\Models\User;
use App\Services\Timeline\TimelineAudience;
use App\Services\Timeline\TimelineFeedPresenter;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('clusters repetitive updated rows of the same subject type', function (): void {
    $presenter = app(TimelineFeedPresenter::class);
    $at = CarbonImmutable::parse('2026-07-08 10:00:00');

    $entries = collect(range(1, 3))->map(fn (int $i): TimelineEntry => new TimelineEntry(
        actorLabel: 'Laurentius',
        actorType: ActorType::Staff,
        entryType: TimelineAudience::ENTRY_ACTIVITY,
        event: 'updated',
        headline: 'updated Supplier Quote SQ-2026-00'.(80 + $i),
        subjectType: 'supplier_quote',
        subjectId: $i,
        subjectNumber: 'SQ-2026-00'.(80 + $i),
        changedFieldCount: 1,
        occurredAt: $at->addMinutes($i),
        properties: ['activity_id' => $i, 'attributes' => ['status' => 'sent']],
    ));

    $groups = $presenter->groupByDay($entries);
    $items = $groups->first()['items'];

    expect($items)->toHaveCount(1)
        ->and($items->first()->isCluster)->toBeTrue()
        ->and($items->first()->count())->toBe(3)
        ->and($items->first()->summaryHeadline)->toBe('3 Supplier Quotes updated');
});

it('filters quote and document lanes', function (): void {
    $presenter = app(TimelineFeedPresenter::class);
    $at = CarbonImmutable::parse('2026-07-08 10:00:00');

    $quote = new TimelineEntry(
        actorLabel: 'Staff',
        actorType: ActorType::Staff,
        entryType: TimelineAudience::ENTRY_ACTIVITY,
        event: 'created',
        headline: 'created Buyer Quote BQ-1',
        subjectType: 'buyer_quote',
        subjectId: 1,
        subjectNumber: 'BQ-1',
        changedFieldCount: 0,
        occurredAt: $at,
    );

    $document = new TimelineEntry(
        actorLabel: 'System',
        actorType: ActorType::System,
        entryType: TimelineAudience::ENTRY_MEDIA,
        event: 'uploaded',
        headline: 'uploaded file.pdf',
        subjectType: 'request',
        subjectId: 1,
        subjectNumber: 'REQ-1',
        changedFieldCount: 0,
        occurredAt: $at,
    );

    $entries = collect([$quote, $document]);

    expect($presenter->filter($entries, TimelineHistoryFilter::Quotes))->toHaveCount(1)
        ->and($presenter->filter($entries, TimelineHistoryFilter::Documents))->toHaveCount(1)
        ->and($presenter->filter($entries, TimelineHistoryFilter::All))->toHaveCount(2);
});

it('defaults today open when mounting the compact timeline', function (): void {
    $admin = User::factory()->withPersonalTeam()->create();
    actingAs($admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($admin->personalTeam());

    $request = Request::factory()->recycle($admin->personalTeam())->create(['creator_id' => $admin->getKey()]);

    livewire(\App\Livewire\RequestHistoryTimeline::class, [
        'request' => $request,
        'compact' => true,
    ])
        ->assertSet('expandedDays', [now()->toDateString()]);
});
