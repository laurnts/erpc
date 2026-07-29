<?php

declare(strict_types=1);

use App\Enums\Erp\PriceBasis;
use App\Services\Erp\Financial\LineCalculator;
use App\Support\Money;

beforeEach(function (): void {
    $this->calc = new LineCalculator;
});

describe('NET price basis (price is tax-exclusive)', function (): void {
    it('adds tax on top of the net price', function (): void {
        $amounts = $this->calc->calculate(
            unitPriceInput: 5200.0,
            priceBasis: PriceBasis::NET,
            taxable: true,
            taxRate: 11.0,
            quantity: 2.0,
            currencyDecimals: 0,
        );

        expect($amounts->unitPriceExcTax)->toBe(5200.0)
            ->and($amounts->taxAmountPerUnit)->toBe(572.0)
            ->and($amounts->lineSubtotal)->toBe(10400.0)
            ->and($amounts->lineTax)->toBe(1144.0)
            ->and($amounts->lineTotal)->toBe(11544.0);
    });
});

describe('GROSS price basis (price includes tax)', function (): void {
    it('extracts tax from the gross price', function (): void {
        $amounts = $this->calc->calculate(
            unitPriceInput: 5772.0,
            priceBasis: PriceBasis::GROSS,
            taxable: true,
            taxRate: 11.0,
            quantity: 2.0,
            currencyDecimals: 0,
        );

        expect($amounts->unitPriceExcTax)->toBe(5200.0)
            ->and($amounts->lineSubtotal)->toBe(10400.0)
            ->and($amounts->lineTax)->toBe(1144.0)
            ->and($amounts->lineTotal)->toBe(11544.0);
    });
});

describe('non-taxable lines', function (): void {
    it('charges no tax and total equals subtotal', function (): void {
        $amounts = $this->calc->calculate(
            unitPriceInput: 5200.0,
            priceBasis: PriceBasis::NET,
            taxable: false,
            taxRate: 11.0,
            quantity: 2.0,
            currencyDecimals: 0,
        );

        expect($amounts->unitPriceExcTax)->toBe(5200.0)
            ->and($amounts->taxAmountPerUnit)->toBe(0.0)
            ->and($amounts->lineSubtotal)->toBe(10400.0)
            ->and($amounts->lineTax)->toBe(0.0)
            ->and($amounts->lineTotal)->toBe(10400.0);
    });
});

describe('zero tax rate', function (): void {
    it('treats a zero rate as no tax regardless of basis', function (): void {
        $net = $this->calc->calculate(5200.0, PriceBasis::NET, true, 0.0, 2.0, 0);
        $gross = $this->calc->calculate(5200.0, PriceBasis::GROSS, true, 0.0, 2.0, 0);

        expect($net->lineTax)->toBe(0.0)
            ->and($net->lineSubtotal)->toBe($net->lineTotal)
            ->and($net->unitPriceExcTax)->toBe(5200.0)
            ->and($gross->unitPriceExcTax)->toBe(5200.0);
    });
});

describe('per-component rounding (drift fix)', function (): void {
    it('rounds each component first so subtotal + tax === total exactly (IDR, 0 dp)', function (): void {
        $amounts = $this->calc->calculate(
            unitPriceInput: 1000.0,
            priceBasis: PriceBasis::GROSS,
            taxable: true,
            taxRate: 11.0,
            quantity: 1.0,
            currencyDecimals: 0,
        );

        expect($amounts->lineSubtotal)->toBe(901.0)
            ->and($amounts->lineTax)->toBe(99.0)
            ->and($amounts->lineTotal)->toBe(1000.0)
            ->and($amounts->lineSubtotal + $amounts->lineTax)->toBe($amounts->lineTotal);
    });

    it('respects the currency precision (2 dp)', function (): void {
        $amounts = $this->calc->calculate(
            unitPriceInput: 10.50,
            priceBasis: PriceBasis::NET,
            taxable: true,
            taxRate: 10.0,
            quantity: 1.0,
            currencyDecimals: 2,
        );

        expect($amounts->lineSubtotal)->toBe(10.50)
            ->and($amounts->lineTax)->toBe(1.05)
            ->and($amounts->lineTotal)->toBe(11.55);
    });
});

describe('exact arithmetic', function (): void {
    it('reconciles subtotal plus tax to total exactly', function (): void {
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal('33.3333', 'IDR'),
            priceBasis: PriceBasis::NET,
            taxable: true,
            taxRate: '11',
            quantity: '7',
            roundingScale: 4,
        );

        expect($amounts->lineSubtotal->plus($amounts->lineTax)->compareTo($amounts->lineTotal))
            ->toBe(0);
    });

    it('derives net from gross without float drift', function (): void {
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal('111', 'IDR'),
            priceBasis: PriceBasis::GROSS,
            taxable: true,
            taxRate: '11',
            quantity: '1',
            roundingScale: 4,
        );

        expect($amounts->unitPriceExcTax->toDecimal())->toBe('100.0000')
            ->and($amounts->taxAmountPerUnit->toDecimal())->toBe('11.0000')
            ->and($amounts->lineTotal->toDecimal())->toBe('111.0000');
    });

    it('rounds to whole units at scale 0 the way buyer quotes do', function (): void {
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal('33.5678', 'IDR'),
            priceBasis: PriceBasis::NET,
            taxable: false,
            taxRate: '0',
            quantity: '3',
            roundingScale: 0,
        );

        expect($amounts->unitPriceExcTax->toDecimal())->toBe('34.0000')
            ->and($amounts->lineSubtotal->toDecimal())->toBe('101.0000');
    });

    it('applies no tax when the line is not taxable', function (): void {
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal('100', 'IDR'),
            priceBasis: PriceBasis::NET,
            taxable: false,
            taxRate: '11',
            quantity: '2',
            roundingScale: 4,
        );

        expect($amounts->lineTax->isZero())->toBeTrue()
            ->and($amounts->lineSubtotal->toDecimal())->toBe('200.0000')
            ->and($amounts->lineTotal->toDecimal())->toBe('200.0000');
    });

    it('applies no tax at a zero rate', function (): void {
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal('100', 'IDR'),
            priceBasis: PriceBasis::GROSS,
            taxable: true,
            taxRate: '0',
            quantity: '1',
            roundingScale: 4,
        );

        expect($amounts->lineTax->isZero())->toBeTrue()
            ->and($amounts->unitPriceExcTax->toDecimal())->toBe('100.0000');
    });

    it('handles fractional quantities', function (): void {
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal('10', 'IDR'),
            priceBasis: PriceBasis::NET,
            taxable: false,
            taxRate: '0',
            quantity: '2.5',
            roundingScale: 4,
        );

        expect($amounts->lineSubtotal->toDecimal())->toBe('25.0000');
    });
});
