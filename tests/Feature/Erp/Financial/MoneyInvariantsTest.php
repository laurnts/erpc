<?php

declare(strict_types=1);

use App\Enums\Erp\PriceBasis;
use App\Services\Erp\Financial\LineCalculator;
use App\Services\Erp\Financial\TotalsCollector;
use App\Services\Erp\Financial\TotalsLine;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * I-M1: for every line, lineSubtotal + lineTax === lineTotal
 * I-M2: for every document, SUM(line subtotals) === document subtotal, and the
 *       same for tax and grand total
 * I-M3: marginAmount === subtotal - costTotal
 * I-M4: no monetary result is ever NaN or infinite
 *
 * Both rounding scales in production use (0 for buyer documents, 4 for supplier
 * documents) are exercised — a rounding scale that broke reconciliation would
 * otherwise only surface on one side of the business.
 */
$prices = ['0.0001', '0.3333', '1', '19.99', '111', '1234.5678', '999999.9999'];
$rates = ['0', '10', '11', '11.5', '21'];
$quantities = ['1', '3', '2.5', '7.3333', '100'];
$scales = [0, 4];

it('I-M1: line subtotal plus tax always equals line total', function () use ($prices, $rates, $quantities, $scales): void {
    $calculator = new LineCalculator;

    foreach ($prices as $price) {
        foreach ($rates as $rate) {
            foreach ($quantities as $quantity) {
                foreach ($scales as $scale) {
                    foreach ([PriceBasis::NET, PriceBasis::GROSS] as $basis) {
                        $amounts = $calculator->calculate(
                            unitPriceInput: Money::fromDecimal($price, 'IDR'),
                            priceBasis: $basis,
                            taxable: true,
                            taxRate: $rate,
                            quantity: $quantity,
                            roundingScale: $scale,
                        );

                        expect($amounts->lineSubtotal->plus($amounts->lineTax)->compareTo($amounts->lineTotal))
                            ->toBe(0, "price={$price} rate={$rate} qty={$quantity} scale={$scale} basis={$basis->name}");
                    }
                }
            }
        }
    }
});

it('I-M2 and I-M3: document totals reconcile to their lines', function () use ($prices, $rates, $scales): void {
    $calculator = new LineCalculator;
    $collector = new TotalsCollector;

    foreach ($rates as $rate) {
        foreach ($scales as $scale) {
            $lines = new Collection;
            $expectedSubtotal = Money::zero('IDR');
            $expectedTax = Money::zero('IDR');

            foreach ($prices as $price) {
                $amounts = $calculator->calculate(
                    unitPriceInput: Money::fromDecimal($price, 'IDR'),
                    priceBasis: PriceBasis::NET,
                    taxable: true,
                    taxRate: $rate,
                    quantity: '3',
                    roundingScale: $scale,
                );

                $lines->push(new TotalsLine(
                    lineSubtotal: $amounts->lineSubtotal,
                    lineTax: $amounts->lineTax,
                    lineTotal: $amounts->lineTotal,
                    costPrice: Money::fromDecimal('1', 'IDR'),
                    quantity: '3',
                ));

                $expectedSubtotal = $expectedSubtotal->plus($amounts->lineSubtotal);
                $expectedTax = $expectedTax->plus($amounts->lineTax);
            }

            $totals = $collector->collect($lines, 'IDR');

            expect($totals->subtotal->compareTo($expectedSubtotal))->toBe(0, "rate={$rate} scale={$scale}")
                ->and($totals->taxTotal->compareTo($expectedTax))->toBe(0)
                ->and($totals->subtotal->plus($totals->taxTotal)->compareTo($totals->grandTotal))->toBe(0)
                ->and($totals->marginAmount->compareTo($totals->subtotal->minus($totals->costTotal)))->toBe(0);
        }
    }
});

it('I-M4: no monetary result is NaN or infinite', function () use ($prices, $rates, $quantities): void {
    $calculator = new LineCalculator;

    foreach ($prices as $price) {
        foreach ($rates as $rate) {
            foreach ($quantities as $quantity) {
                $amounts = $calculator->calculate(
                    unitPriceInput: Money::fromDecimal($price, 'IDR'),
                    priceBasis: PriceBasis::GROSS,
                    taxable: true,
                    taxRate: $rate,
                    quantity: $quantity,
                    roundingScale: 4,
                );

                foreach ([$amounts->lineSubtotal, $amounts->lineTax, $amounts->lineTotal] as $money) {
                    expect(is_finite($money->toFloat()))->toBeTrue();
                }
            }
        }
    }
});
