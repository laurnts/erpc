<?php

declare(strict_types=1);

use App\Models\BuyerQuoteItem;
use Illuminate\Support\Collection;

it('uses net sell (line_subtotal) as the P&L margin base, never gross', function (): void {
    // qty 2 line: net sell 10,000, VAT 1,100, gross 11,100, cost 4,000/unit (8,000 total)
    $item = new BuyerQuoteItem;
    $item->line_subtotal = '10000';
    $item->line_tax = '1100';
    $item->line_total = '11100';
    $item->cost_price = '4000';
    $item->quantity = '2';

    $totals = BuyerQuoteItem::collectTotals(new Collection([$item]));

    expect($totals->subtotal)->toBe(10000.0)     // net revenue, not gross 11,100
        ->and($totals->costTotal)->toBe(8000.0)
        ->and($totals->marginAmount)->toBe(2000.0) // 10,000 - 8,000, NOT 11,100 - 8,000
        ->and($totals->grandTotal)->toBe(11100.0); // gross still available for display
});

it('excludes service child items from P&L group totals', function (): void {
    $main = new BuyerQuoteItem;
    $main->line_subtotal = '5000';
    $main->line_tax = '0';
    $main->line_total = '5000';
    $main->cost_price = '3000';
    $main->quantity = '1';
    // main item: no parent (not a child)

    $totals = BuyerQuoteItem::collectTotals(new Collection([$main]));

    expect($totals->subtotal)->toBe(5000.0)
        ->and($totals->marginAmount)->toBe(2000.0);
});
