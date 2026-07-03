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
