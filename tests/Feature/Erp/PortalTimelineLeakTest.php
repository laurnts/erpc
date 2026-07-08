<?php

declare(strict_types=1);

use App\Enums\ActorType;
use App\Enums\RequestStage;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerPayment;
use App\Models\Company;
use App\Models\GoodsReceiveBatch;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\Shipment;
use App\Models\SupplierInvoice;
use App\Models\SupplierOrder;
use App\Models\SupplierPayment;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Services\CustomerPortal\CustomerRequestStagePresenter;
use App\Services\Timeline\PortalTimelineSource;
use App\Services\Timeline\TimelineAudience;
use App\Services\Timeline\TimelineParty;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Notification::fake();

    $this->staff = User::factory()->withPersonalTeam()->create(['name' => 'Staff Member Olivia']);
    $this->team = $this->staff->personalTeam();

    actingAs($this->staff);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team);

    $this->source = app(PortalTimelineSource::class);
});

/**
 * Seed a request whose child tree spans both buyer-facing documents and
 * internal/supplier-side documents, plus staff-proof and buyer media.
 *
 * @return array<string, mixed>
 */
function seedLeakRequest(Tests\TestCase $test): array
{
    $buyer = Company::factory()->buyer()->recycle($test->team)->create();
    $supplier = Company::factory()->supplier()->recycle($test->team)->create();

    $request = Request::factory()->recycle($test->team)->create([
        'buyer_id' => $buyer->getKey(),
        'stage' => RequestStage::DRAFT,
    ]);

    // Buyer-facing documents (should surface).
    $buyerQuote = \App\Models\BuyerQuote::factory()->recycle($test->team)->for($request)->sent()->create([
        'buyer_id' => $buyer->getKey(),
    ]);
    $buyerInvoice = BuyerInvoice::factory()->recycle($test->team)->for($request)->create();
    $buyerPayment = BuyerPayment::factory()->recycle($test->team)->create([
        'team_id' => $test->team->getKey(),
        'buyer_invoice_id' => $buyerInvoice->getKey(),
    ]);
    $outboundShipment = Shipment::factory()->recycle($test->team)->outbound()->create([
        'request_id' => $request->getKey(),
    ]);

    // Internal / supplier-side documents (must never surface for the buyer).
    $buyerOrder = BuyerOrder::factory()->recycle($test->team)->for($request)->create([
        'buyer_id' => $buyer->getKey(),
    ]);
    $supplierQuote = SupplierQuote::factory()->recycle($test->team)->for($request)->create([
        'supplier_id' => $supplier->getKey(),
    ]);
    $supplierOrder = SupplierOrder::factory()->recycle($test->team)->for($request)->create([
        'supplier_id' => $supplier->getKey(),
    ]);
    $supplierInvoice = SupplierInvoice::factory()->recycle($test->team)->for($request)->create([
        'supplier_id' => $supplier->getKey(),
    ]);
    $supplierPayment = SupplierPayment::factory()->recycle($test->team)->create([
        'team_id' => $test->team->getKey(),
        'supplier_invoice_id' => $supplierInvoice->getKey(),
    ]);
    $inboundShipment = Shipment::factory()->recycle($test->team)->inbound()->create([
        'request_id' => $request->getKey(),
    ]);
    $evaluation = QuotationEvaluation::factory()->recycle($test->team)->for($request)->create();
    $pnl = ProfitAndLoss::factory()->recycle($test->team)->for($request)->create();
    $goodsReceive = GoodsReceiveBatch::query()->create([
        'request_id' => $request->getKey(),
        'supplier_order_id' => $supplierOrder->getKey(),
        'user_id' => $test->staff->getKey(),
        'media_ids' => [],
    ]);

    // A genuine buyer upload (should surface) and staff-proof media (must not).
    $request->addMediaFromString('genuine-buyer-file')
        ->usingFileName('buyer-signed-po.pdf')
        ->withCustomProperties([
            'uploader_id' => $test->staff->getKey(),
            'uploader_actor_type' => ActorType::Buyer->value,
        ])
        ->toMediaCollection('attachments');

    $request->addMediaFromString('internal-proof')
        ->usingFileName('internal-cost-proof.pdf')
        ->withCustomProperties([
            'uploader_id' => $test->staff->getKey(),
            'uploader_actor_type' => ActorType::Staff->value,
        ])
        ->toMediaCollection('attachments');

    $request->addMediaFromString('legacy-unstamped')
        ->usingFileName('unstamped-legacy.pdf')
        ->toMediaCollection('attachments');

    // Drive a stage change so a stage value flows through the activity log.
    $request->update(['stage' => RequestStage::PREPARING_BUYER_QUOTE]);

    return [
        'buyer' => $buyer,
        'request' => $request,
        'buyerQuote' => $buyerQuote,
        'buyerInvoice' => $buyerInvoice,
        'buyerPayment' => $buyerPayment,
        'outboundShipment' => $outboundShipment,
        'inboundShipment' => $inboundShipment,
        'buyerOrder' => $buyerOrder,
        'supplierQuote' => $supplierQuote,
        'supplierOrder' => $supplierOrder,
        'supplierInvoice' => $supplierInvoice,
        'supplierPayment' => $supplierPayment,
        'evaluation' => $evaluation,
        'pnl' => $pnl,
        'goodsReceive' => $goodsReceive,
    ];
}

it('never surfaces supplier, internal, or inbound subjects in the buyer feed', function (): void {
    $seed = seedLeakRequest($this);

    $entries = $this->source->forParty($seed['request'], TimelineParty::buyer($seed['buyer']->getKey()));

    $forbiddenTypes = [
        'supplier_quote', 'supplier_order', 'supplier_invoice', 'supplier_payment',
        'quotation_evaluation', 'profit_and_loss', 'buyer_order', 'goods_receive_batch',
    ];

    foreach ($entries as $entry) {
        expect($forbiddenTypes)->not->toContain($entry->subjectType);
    }

    // The inbound shipment id must be absent even though 'shipment' is allow-listed.
    $shipmentIds = collect($entries)->where('subjectType', 'shipment')->pluck('subjectId')->all();

    expect($shipmentIds)->not->toContain($seed['inboundShipment']->getKey())
        ->and($shipmentIds)->toContain($seed['outboundShipment']->getKey());
});

it('drops staff-proof and unstamped uploads, keeping only the genuine buyer upload', function (): void {
    $seed = seedLeakRequest($this);

    $entries = $this->source->forParty($seed['request'], TimelineParty::buyer($seed['buyer']->getKey()));

    $mediaHeadlines = collect($entries)
        ->where('entryType', TimelineAudience::ENTRY_MEDIA)
        ->pluck('headline')
        ->all();

    expect($mediaHeadlines)->toHaveCount(1)
        ->and($mediaHeadlines[0])->toContain('buyer-signed-po.pdf');

    $rendered = view('timeline.portal-timeline', ['entries' => $entries])->render();

    expect($rendered)->not->toContain('internal-cost-proof.pdf')
        ->and($rendered)->not->toContain('unstamped-legacy.pdf');
});

it('redacts staff causers and raw stage values in the rendered feed', function (): void {
    $seed = seedLeakRequest($this);

    $entries = $this->source->forParty($seed['request'], TimelineParty::buyer($seed['buyer']->getKey()));
    $rendered = view('timeline.portal-timeline', ['entries' => $entries])->render();

    $presenterLabel = app(CustomerRequestStagePresenter::class)->labelForStage(RequestStage::PREPARING_BUYER_QUOTE);

    // Stage is presented via the customer label, never the raw enum value.
    expect($rendered)->toContain($presenterLabel)
        ->and($rendered)->not->toContain(RequestStage::PREPARING_BUYER_QUOTE->value);

    // No staff person name leaks; the causer collapses to the generic label.
    expect($rendered)->not->toContain('Staff Member Olivia')
        ->and($rendered)->toContain('Your team');

    // The buyer's own upload attributes to "You" (not merely "Your team").
    expect(collect($entries)->pluck('actorLabel')->all())->toContain('You');

    // No internal or sysadmin links.
    expect($rendered)->not->toContain('filament.app.')
        ->and($rendered)->not->toContain('sysadmin');

    foreach ($entries as $entry) {
        expect($entry->url)->toBeNull()
            ->and($entry->actorLabel)->not->toBe('Staff Member Olivia');
    }
});

it('keeps the buyer subject-type set a strict subset of the internal set', function (): void {
    $seed = seedLeakRequest($this);

    $entries = $this->source->forParty($seed['request'], TimelineParty::buyer($seed['buyer']->getKey()));
    $subjectTypes = collect($entries)->pluck('subjectType')->unique()->values()->all();

    expect($subjectTypes)->not->toBeEmpty();

    foreach ($subjectTypes as $subjectType) {
        expect(TimelineAudience::INTERNAL_SUBJECT_TYPES)->toContain($subjectType);
    }

    expect(count($subjectTypes))->toBeLessThan(count(TimelineAudience::INTERNAL_SUBJECT_TYPES));
});
