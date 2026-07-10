<?php

declare(strict_types=1);

use App\Enums\PrepaymentType;
use App\Models\BuyerQuote;
use App\Models\BuyerQuotePaymentTerm;
use App\Support\PaymentTermsDescription;
use Illuminate\Support\Collection;

test('formats prepayment and payment terms as numbered lines', function (): void {
    $quote = new BuyerQuote([
        'prepayment_type' => PrepaymentType::PERCENT,
        'prepayment_percent' => 0,
        'prepayment_amount' => '50.0000',
    ]);

    $quote->setRelation('paymentTerms', new Collection([
        new BuyerQuotePaymentTerm(['due_days' => 30, 'percentage' => 50, 'sort_order' => 0]),
        new BuyerQuotePaymentTerm(['due_days' => 7, 'percentage' => 50, 'sort_order' => 1]),
    ]));

    expect(PaymentTermsDescription::linesFromBuyerQuote($quote))->toBe([
        '1. Prepayment: 50%',
        '2. Payment term 1: 30 days - 50%',
        '3. Payment term 2: 7 days - 50%',
    ]);
});

test('effective prepayment percent falls back to prepayment amount', function (): void {
    $quote = new BuyerQuote([
        'prepayment_type' => PrepaymentType::PERCENT,
        'prepayment_percent' => 0,
        'prepayment_amount' => '50.0000',
    ]);

    expect(PaymentTermsDescription::effectivePrepaymentPercent($quote))->toBe(50);
});

test('omits prepayment line when prepayment is zero', function (): void {
    $quote = new BuyerQuote([
        'prepayment_type' => PrepaymentType::PERCENT,
        'prepayment_percent' => 0,
        'prepayment_amount' => '0.0000',
    ]);

    $quote->setRelation('paymentTerms', new Collection([
        new BuyerQuotePaymentTerm(['due_days' => 30, 'percentage' => 100, 'sort_order' => 0]),
    ]));

    expect(PaymentTermsDescription::linesFromBuyerQuote($quote))->toBe([
        '1. Payment term 1: 30 days - 100%',
    ]);
});
