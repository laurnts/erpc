<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Team;
use App\Models\User;
use App\Services\Erp\PdfGenerationService;

it('sources buyer-quote PDF totals from the stored document, excluding service child rows', function (): void {
    $team = Team::factory()->create();
    $user = User::factory()->recycle($team)->create();
    $this->actingAs($user);

    $buyer = Company::factory()->buyer()->recycle($team)->create();
    $currency = Currency::factory()->create();
    $request = Request::factory()->recycle($team)->recycle($buyer)->create();
    $mainReqItem = RequestItem::factory()->recycle($request)->create([
        'parent_id' => null,
        'item_type' => ItemType::SERVICE,
    ]);
    $childReqItem = RequestItem::factory()->recycle($request)->create(['parent_id' => $mainReqItem->getKey()]);

    $quote = BuyerQuote::factory()
        ->recycle($team)
        ->recycle($buyer)
        ->forRequest($request)
        ->withCurrency($currency)
        ->create();

    // Main item: net 1,000 + Tax 10% => line total 1,100 (counts toward the stored total)
    BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
        'request_item_id' => $mainReqItem->getKey(),
        'quantity' => '1', 'unit_price' => '1000', 'cost_price' => '600',
        'tax_rate' => '10', 'is_tax_inclusive' => true,
    ]);
    // Child/detail row: display-only breakdown, must NOT inflate the total
    BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
        'request_item_id' => $childReqItem->getKey(),
        'quantity' => '1', 'unit_price' => '500', 'cost_price' => '300',
        'tax_rate' => '0', 'is_tax_inclusive' => false,
    ]);

    $quote->recalculateTotals();
    $quote->refresh();

    $data = app(PdfGenerationService::class)->buildBuyerQuotePdfData($quote);

    expect((float) $quote->total)->toBe(1100.0)          // stored: main item only
        ->and($data['processedTotal'])->toBe(1100.0)      // PDF total == stored, not 1,600
        ->and($data['processedSubtotal'])->toBe(1000.0)
        ->and($data['processedTaxTotal'])->toBe(100.0);
});

it('orders buyer-quote PDF items hierarchically under their parent', function (): void {
    $team = Team::factory()->create();
    $user = User::factory()->recycle($team)->create();
    $this->actingAs($user);

    $buyer = Company::factory()->buyer()->recycle($team)->create();
    $currency = Currency::factory()->create();
    $request = Request::factory()->recycle($team)->recycle($buyer)->create();
    $mainReqItemA = RequestItem::factory()->recycle($request)->create(['parent_id' => null]);
    $childReqItemA1 = RequestItem::factory()->recycle($request)->create(['parent_id' => $mainReqItemA->getKey()]);
    $childReqItemA2 = RequestItem::factory()->recycle($request)->create(['parent_id' => $mainReqItemA->getKey()]);
    $mainReqItemB = RequestItem::factory()->recycle($request)->create(['parent_id' => null]);

    $quote = BuyerQuote::factory()
        ->recycle($team)
        ->recycle($buyer)
        ->forRequest($request)
        ->withCurrency($currency)
        ->create();

    BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
        'request_item_id' => $mainReqItemA->getKey(),
        'description' => 'Main work A',
        'sort_order' => 1,
    ]);
    BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
        'request_item_id' => $childReqItemA1->getKey(),
        'description' => 'Child work A1',
        'sort_order' => 2,
    ]);
    BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
        'request_item_id' => $mainReqItemB->getKey(),
        'description' => 'Main work B',
        'sort_order' => 3,
    ]);
    BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
        'request_item_id' => $childReqItemA2->getKey(),
        'description' => 'Child work A2',
        'sort_order' => 4,
    ]);

    $data = app(PdfGenerationService::class)->buildBuyerQuotePdfData($quote);
    $html = view('pdf.buyer-quote', $data)->render();

    $mainAPos = strpos($html, 'Main work A');
    $childA1Pos = strpos($html, 'Child work A1');
    $childA2Pos = strpos($html, 'Child work A2');
    $mainBPos = strpos($html, 'Main work B');

    expect($mainAPos)->not->toBeFalse()
        ->and($childA1Pos)->not->toBeFalse()
        ->and($childA2Pos)->not->toBeFalse()
        ->and($mainBPos)->not->toBeFalse()
        ->and($mainAPos)->toBeLessThan($childA1Pos)
        ->and($childA1Pos)->toBeLessThan($childA2Pos)
        ->and($childA2Pos)->toBeLessThan($mainBPos);
});
