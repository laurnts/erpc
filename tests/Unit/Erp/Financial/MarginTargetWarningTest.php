<?php

declare(strict_types=1);

use App\Models\BuyerQuoteItem;
use Illuminate\Support\Collection;

/**
 * Mirrors the banner trigger in pnl-selected-items.blade.php: the overall
 * document is flagged when the rounded blended margin is below target.
 */
function overallBelowTarget(Collection $items, float $target): bool
{
    $totals = BuyerQuoteItem::collectTotals($items, hasItemHierarchy: false);

    return (int) round($totals->marginPercent) < $target;
}

it('reports the display margin percent on the canonical sell base, not cost', function (): void {
    // cost 100, sell 150: sell-based = (150-100)/150 = 33%, cost-based would be 50%
    $item = new BuyerQuoteItem;
    $item->cost_price = '100';
    $item->unit_price_exc_tax = '150';

    expect($item->getDisplayMarginPercent())->toBe(33);
});

it('does not flag a line whose margin meets the target', function (): void {
    // cost 97, sell 100: sell-based = 3%
    $item = new BuyerQuoteItem;
    $item->cost_price = '97';
    $item->unit_price_exc_tax = '100';

    expect($item->getDisplayMarginPercent())->toBe(3)
        ->and($item->isMarginBelowTarget(3.0))->toBeFalse();
});

it('flags a line whose margin falls below the target', function (): void {
    // cost 98, sell 100: sell-based = 2%
    $item = new BuyerQuoteItem;
    $item->cost_price = '98';
    $item->unit_price_exc_tax = '100';

    expect($item->getDisplayMarginPercent())->toBe(2)
        ->and($item->isMarginBelowTarget(3.0))->toBeTrue();
});

it('does not flag a healthy margin above the target', function (): void {
    // cost 90, sell 100: sell-based = 10%
    $item = new BuyerQuoteItem;
    $item->cost_price = '90';
    $item->unit_price_exc_tax = '100';

    expect($item->isMarginBelowTarget(3.0))->toBeFalse();
});

it('flags the overall document when the blended margin rounds below target', function (): void {
    // two lines: net sell 100 each, cost 99 each => blended margin (200-198)/200 = 1%
    $items = new Collection([
        tap(new BuyerQuoteItem, function (BuyerQuoteItem $i): void {
            $i->line_subtotal = '100';
            $i->cost_price = '99';
            $i->quantity = '1';
        }),
        tap(new BuyerQuoteItem, function (BuyerQuoteItem $i): void {
            $i->line_subtotal = '100';
            $i->cost_price = '99';
            $i->quantity = '1';
        }),
    ]);

    expect(overallBelowTarget($items, 3.0))->toBeTrue();
});

it('does not flag the overall document when the blended margin meets target', function (): void {
    // net sell 100 each, cost 90 each => blended margin (200-180)/200 = 10%
    $items = new Collection([
        tap(new BuyerQuoteItem, function (BuyerQuoteItem $i): void {
            $i->line_subtotal = '100';
            $i->cost_price = '90';
            $i->quantity = '1';
        }),
        tap(new BuyerQuoteItem, function (BuyerQuoteItem $i): void {
            $i->line_subtotal = '100';
            $i->cost_price = '90';
            $i->quantity = '1';
        }),
    ]);

    expect(overallBelowTarget($items, 3.0))->toBeFalse();
});
