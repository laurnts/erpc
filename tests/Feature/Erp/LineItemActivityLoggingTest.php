<?php

declare(strict_types=1);

use App\Enums\ActorType;
use App\Enums\BuyerQuoteStatus;
use App\Models\ActivityLog;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\RequestItem;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team = $this->admin->personalTeam();

    actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team);
});

it('logs creation of a buyer quote item as its own subject', function (): void {
    ActivityLog::query()->delete();

    $item = BuyerQuoteItem::factory()->recycle($this->team)->create();

    $activity = ActivityLog::query()
        ->where('subject_type', 'buyer_quote_item')
        ->where('subject_id', $item->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->team_id)->toBe($this->team->id)
        ->and($activity->properties->get('attributes'))->toHaveKey('unit_price');
});

it('logs a buyer quote item price change with old and new values', function (): void {
    $item = BuyerQuoteItem::factory()->recycle($this->team)->create([
        'unit_price' => '100.0000',
        'unit_price_exc_tax' => '100.0000',
        'tax_rate' => '0.0000',
        'is_tax_inclusive' => false,
    ]);

    ActivityLog::query()->delete();

    $item->update(['unit_price' => '250.0000']);

    $activity = ActivityLog::query()
        ->where('subject_type', 'buyer_quote_item')
        ->where('subject_id', $item->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('attributes'))->toHaveKey('unit_price', '250.0000')
        ->and($activity->properties->get('old'))->toHaveKey('unit_price', '100.0000');
});

it('does not log a cosmetic-only change to a buyer quote item', function (): void {
    $item = BuyerQuoteItem::factory()->recycle($this->team)->create([
        'unit_price' => '100.0000',
        'unit_price_exc_tax' => '100.0000',
    ]);

    ActivityLog::query()->delete();

    $item->update(['notes' => 'internal restock reminder']);

    expect(ActivityLog::query()->count())->toBe(0);
});

it('logs a request item quantity change with old and new values', function (): void {
    $item = RequestItem::factory()->recycle($this->team)->create(['quantity' => '5.0000']);

    ActivityLog::query()->delete();

    $item->update(['quantity' => '9.0000']);

    $activity = ActivityLog::query()
        ->where('subject_type', 'request_item')
        ->where('subject_id', $item->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('attributes'))->toHaveKey('quantity', '9.0000')
        ->and($activity->properties->get('old'))->toHaveKey('quantity', '5.0000');
});

it('does not log a cosmetic-only change to a request item', function (): void {
    $item = RequestItem::factory()->recycle($this->team)->create();

    ActivityLog::query()->delete();

    $item->update(['notes' => 'clarify spec with buyer']);

    expect(ActivityLog::query()->count())->toBe(0);
});

it('logs a supplier quote item award (is_selected) change', function (): void {
    $item = SupplierQuoteItem::factory()->recycle($this->team)->create(['is_selected' => false]);

    ActivityLog::query()->delete();

    $item->update(['is_selected' => true]);

    $activity = ActivityLog::query()
        ->where('subject_type', 'supplier_quote_item')
        ->where('subject_id', $item->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('attributes'))->toHaveKey('is_selected', true)
        ->and($activity->properties->get('old'))->toHaveKey('is_selected', false);
});

it('stamps the parent header team on item rows when no ambient context resolves', function (): void {
    $item = BuyerQuoteItem::factory()->recycle($this->team)->create([
        'unit_price' => '100.0000',
        'unit_price_exc_tax' => '100.0000',
        'tax_rate' => '0.0000',
        'is_tax_inclusive' => false,
    ]);

    auth()->logout();
    Filament::setTenant(null);
    ActivityLog::query()->delete();

    $item->update(['unit_price' => '300.0000']);

    $activity = ActivityLog::query()
        ->where('subject_type', 'buyer_quote_item')
        ->where('subject_id', $item->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->team_id)->toBe($this->team->id);
});

it('suppresses item rows when no team resolves from parent or context', function (): void {
    $item = BuyerQuoteItem::factory()->recycle($this->team)->create([
        'unit_price' => '100.0000',
        'unit_price_exc_tax' => '100.0000',
        'tax_rate' => '0.0000',
        'is_tax_inclusive' => false,
    ]);

    $item->buyerQuote->delete();

    auth()->logout();
    Filament::setTenant(null);
    ActivityLog::query()->delete();

    $item->refresh()->update(['unit_price' => '300.0000']);

    expect(ActivityLog::query()->count())->toBe(0);
});

it('renders line-item parent context in the event detail view', function (): void {
    $item = BuyerQuoteItem::factory()->recycle($this->team)->create([
        'description' => 'Steel pipe DN50',
        'unit_price' => '100.0000',
        'unit_price_exc_tax' => '100.0000',
        'tax_rate' => '0.0000',
        'is_tax_inclusive' => false,
    ]);

    ActivityLog::query()->delete();

    $item->update(['unit_price' => '250.0000']);

    /** @var ActivityLog $activity */
    $activity = ActivityLog::query()
        ->where('subject_type', 'buyer_quote_item')
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    $html = view('filament.event-log-detail', [
        'activity' => $activity->loadMissing(['causer', 'subject']),
    ])->render();

    expect($html)->toContain('Belongs To')
        ->toContain('Buyer Quote #'.$item->buyer_quote_id)
        ->toContain('Steel pipe DN50')
        ->toContain('Unit Price');
});

it('logs a supplier portal price change with the supplier actor and parent team', function (): void {
    $item = SupplierQuoteItem::factory()->recycle($this->team)->create([
        'unit_price' => '80.0000',
        'tax_rate' => '0.0000',
        'is_tax_inclusive' => false,
    ]);

    $portalUser = User::factory()->create();
    auth()->logout();
    $this->actingAs($portalUser, 'supplier');
    Filament::setCurrentPanel('supplier');
    Filament::setTenant(null);
    ActivityLog::query()->delete();

    $item->update(['unit_price' => '95.0000']);

    $activity = ActivityLog::query()
        ->where('subject_type', 'supplier_quote_item')
        ->where('subject_id', $item->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->actor_type)->toBe(ActorType::Supplier)
        ->and($activity->causer_id)->toBe($portalUser->id)
        ->and($activity->team_id)->toBe($this->team->id)
        ->and($activity->properties->get('attributes'))->toHaveKey('unit_price', '95.0000');
});

it('logs one header row and zero item rows for a whole-document delete', function (): void {
    $item = BuyerQuoteItem::factory()->recycle($this->team)->create([
        'unit_price' => '100.0000',
        'unit_price_exc_tax' => '100.0000',
    ]);

    /** @var BuyerQuote $quote */
    $quote = $item->buyerQuote;

    ActivityLog::query()->delete();

    $quote->delete();

    expect(ActivityLog::query()->where('subject_type', 'buyer_quote')->where('event', 'deleted')->count())->toBe(1)
        ->and(ActivityLog::query()->where('subject_type', 'buyer_quote_item')->count())->toBe(0);
});

it('logs baseline creates and no source churn when converting an accepted quote to an order', function (): void {
    $item = BuyerQuoteItem::factory()->recycle($this->team)->create([
        'unit_price' => '100.0000',
        'unit_price_exc_tax' => '100.0000',
    ]);

    /** @var BuyerQuote $quote */
    $quote = $item->buyerQuote;
    $quote->update(['status' => BuyerQuoteStatus::ACCEPTED]);

    ActivityLog::query()->delete();

    $order = BuyerOrder::createFromQuote($quote->fresh());

    expect($order->items()->count())->toBeGreaterThan(0)
        ->and(ActivityLog::query()->where('subject_type', 'buyer_order_item')->where('event', 'created')->count())
        ->toBe($order->items()->count())
        ->and(ActivityLog::query()->where('subject_type', 'buyer_quote_item')->count())->toBe(0);
});

it('logs an exchange_rate change on a buyer quote header', function (): void {
    $quote = BuyerQuote::factory()->recycle($this->team)->create(['exchange_rate' => '1.00000000']);

    ActivityLog::query()->delete();

    $quote->update(['exchange_rate' => '1.25000000']);

    $activity = ActivityLog::query()
        ->where('subject_type', 'buyer_quote')
        ->where('subject_id', $quote->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('attributes'))->toHaveKey('exchange_rate', '1.25000000')
        ->and($activity->properties->get('old'))->toHaveKey('exchange_rate', '1.00000000');
});
