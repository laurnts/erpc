<?php

declare(strict_types=1);

use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProfitAndLoss;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Team;
use App\Models\User;

it('renders the P&L PDF using net margin, excluding VAT from profit', function (): void {
    $team = Team::factory()->create();
    $user = User::factory()->recycle($team)->create();
    $this->actingAs($user);

    $buyer = Company::factory()->buyer()->recycle($team)->create();
    $currency = Currency::factory()->create();
    $request = Request::factory()->recycle($team)->recycle($buyer)->create();
    $requestItem = RequestItem::factory()->recycle($request)->create();

    $quote = BuyerQuote::factory()
        ->recycle($team)
        ->recycle($buyer)
        ->forRequest($request)
        ->withCurrency($currency)
        ->create();

    // Net sell 1,000; "+ Tax" 10% => gross 1,100; cost 600 => net margin 400 (not gross 500).
    BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
        'request_item_id' => $requestItem->getKey(),
        'quantity' => '1',
        'unit_price' => '1000',
        'cost_price' => '600',
        'tax_rate' => '10',
        'is_tax_inclusive' => true,
    ]);

    $pnl = ProfitAndLoss::factory()->forBuyerQuote($quote)->create();

    $html = view('pdf.profit-and-loss', [
        'pnl' => $pnl->load(['request', 'buyerQuote.currency', 'team']),
        'company' => ['name' => 'Test Co', 'address' => '', 'phone' => '', 'email' => ''],
    ])->render();

    expect($html)->toContain('400.00')      // net margin (1,000 - 600)
        ->and($html)->toContain('1,000.00')  // net sell (revenue)
        ->and($html)->toContain('1,100.00')  // gross line-total footer
        ->and($html)->not->toContain('500.00'); // the old VAT-in-profit margin
});
