<?php

declare(strict_types=1);

use App\Enums\ActorType;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\Request;
use App\Models\User;
use App\Services\Timeline\PortalTimelineSource;
use App\Services\Timeline\RequestTimelineSource;
use App\Services\Timeline\TimelineParty;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team = $this->admin->personalTeam();

    actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team);

    $this->source = app(RequestTimelineSource::class);
});

/**
 * Seed a request + buyer quote + one buyer-quote line, then clear the log so a
 * test only sees the rows it writes afterwards.
 *
 * @return array{request: Request, quote: BuyerQuote, item: BuyerQuoteItem}
 */
function seedQuoteLine(Tests\TestCase $test, array $itemAttributes = []): array
{
    $request = Request::factory()->recycle($test->team)->create(['creator_id' => $test->admin->getKey()]);
    $quote = BuyerQuote::factory()->recycle($test->team)->for($request)->create([
        'buyer_id' => $request->buyer_id,
    ]);
    $item = BuyerQuoteItem::factory()->recycle($test->team)->for($quote)->create(array_merge([
        'description' => 'Steel pipe',
        'unit_price' => '100.0000',
        'unit_price_exc_tax' => '100.0000',
        'cost_price' => '50.0000',
        'tax_rate' => '0.0000',
        'is_tax_inclusive' => false,
        'article_id' => null,
    ], $itemAttributes));

    ActivityLog::query()->delete();

    return ['request' => $request, 'quote' => $quote, 'item' => $item];
}

it('surfaces a buyer-quote line unit_price change under its parent, attributed with old and new', function (): void {
    ['request' => $request, 'quote' => $quote, 'item' => $item] = seedQuoteLine($this);

    $item->update(['unit_price' => '50.0000']);

    $entries = collect($this->source->entries($request, TimelineParty::staff())->items());
    $line = $entries->firstWhere('subjectType', 'buyer_quote_item');

    expect($line)->not->toBeNull()
        ->and($line->event)->toBe('updated')
        ->and($line->actorType)->toBe(ActorType::Staff)
        ->and($line->actorLabel)->toBe($this->admin->name)
        ->and($line->subjectNumber)->toBe($quote->quote_number)
        ->and($line->headline)->toContain('Steel pipe')
        ->and($line->headline)->toContain($quote->quote_number)
        ->and($line->headline)->toContain('unit_price')
        ->and($line->properties['parent_type'])->toBe('buyer_quote')
        ->and($line->properties['parent_id'])->toBe($quote->getKey())
        ->and($line->properties['attributes'])->toHaveKey('unit_price', '50.0000')
        ->and($line->properties['old'])->toHaveKey('unit_price', '100.0000');
});

it('surfaces a deleted line with a full snapshot even though its row is hard-deleted', function (): void {
    ['request' => $request, 'item' => $item] = seedQuoteLine($this);

    $item->delete();

    $entries = collect($this->source->entries($request, TimelineParty::staff())->items());
    $line = $entries->firstWhere('subjectType', 'buyer_quote_item');

    expect($line)->not->toBeNull()
        ->and($line->event)->toBe('deleted')
        ->and($line->headline)->toContain('removed')
        ->and($line->headline)->toContain('Steel pipe')
        ->and($line->changedFieldCount)->toBeGreaterThan(0)
        ->and($line->properties['old'])->toHaveKey('unit_price', '100.0000')
        ->and($line->properties['old'])->toHaveKey('quantity')
        ->and($line->properties['old'])->toHaveKey('line_total');
});

it('resolves an article swap to human labels old to new', function (): void {
    $articleA = Article::factory()->recycle($this->team)->create(['code' => 'A-100', 'name' => 'Pump']);
    $articleB = Article::factory()->recycle($this->team)->create(['code' => 'B-200', 'name' => 'Valve']);

    ['request' => $request, 'item' => $item] = seedQuoteLine($this, ['article_id' => $articleA->getKey()]);

    $item->update(['article_id' => $articleB->getKey()]);

    $entries = collect($this->source->entries($request, TimelineParty::staff())->items());
    $line = $entries->firstWhere('subjectType', 'buyer_quote_item');

    expect($line)->not->toBeNull()
        ->and($line->properties['labels']['article_id']['old'])->toBe('[A-100] Pump')
        ->and($line->properties['labels']['article_id']['new'])->toBe('[B-200] Valve')
        ->and($line->headline)->toContain('[A-100] Pump')
        ->and($line->headline)->toContain('[B-200] Valve');
});

it('does not surface a money row for a cosmetic edit on a legacy-mispriced line', function (): void {
    ['request' => $request, 'item' => $item] = seedQuoteLine($this);

    // Simulate a legacy row whose stored net price drifted from unit_price,
    // so the observer restates derived money figures on the next save.
    $item->updateQuietly(['unit_price_exc_tax' => '40.0000']);
    ActivityLog::query()->delete();

    $item->update(['notes' => 'internal restock reminder']);

    $entries = collect($this->source->entries($request, TimelineParty::staff())->items());

    expect($entries->firstWhere('subjectType', 'buyer_quote_item'))->toBeNull()
        ->and(ActivityLog::query()->where('subject_type', 'buyer_quote_item')->count())->toBe(0);
});

it('never surfaces line-item entries in the buyer portal feed', function (): void {
    ['request' => $request, 'item' => $item] = seedQuoteLine($this);

    $item->update(['unit_price' => '75.0000']);

    // The internal feed sees the line...
    $internal = collect($this->source->entries($request, TimelineParty::staff())->items());
    expect($internal->firstWhere('subjectType', 'buyer_quote_item'))->not->toBeNull();

    // ...but the buyer portal feed must carry zero item-level entries.
    $portal = app(PortalTimelineSource::class);
    $buyerEntries = $portal->forParty($request, TimelineParty::buyer($request->buyer_id));

    $itemEntries = collect($buyerEntries)
        ->filter(fn ($entry): bool => str_ends_with($entry->subjectType, '_item'));

    expect($itemEntries)->toBeEmpty();
});
