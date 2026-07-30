<?php

declare(strict_types=1);

use App\Models\BuyerCreditLimitRequest;
use App\Models\Company;

test('company has credit limit requests relationship', function (): void {
    $buyer = Company::factory()->buyer()->create();
    $request1 = BuyerCreditLimitRequest::factory()->create([
        'buyer_id' => $buyer->getKey(),
    ]);
    $request2 = BuyerCreditLimitRequest::factory()->create([
        'buyer_id' => $buyer->getKey(),
    ]);

    expect($buyer->creditLimitRequests)->toHaveCount(2)
        ->and($buyer->creditLimitRequests->pluck('id')->toArray())->toContain($request1->getKey(), $request2->getKey());
});

test('company pending credit limit request returns pending request', function (): void {
    $buyer = Company::factory()->buyer()->create();
    $pendingRequest = BuyerCreditLimitRequest::factory()->pending()->create([
        'buyer_id' => $buyer->getKey(),
    ]);
    BuyerCreditLimitRequest::factory()->approved()->create([
        'buyer_id' => $buyer->getKey(),
    ]);

    expect($buyer->pendingCreditLimitRequest())->toBeInstanceOf(BuyerCreditLimitRequest::class)
        ->and($buyer->pendingCreditLimitRequest()->getKey())->toBe($pendingRequest->getKey());
});

test('company pending credit limit request returns null when no pending request', function (): void {
    $buyer = Company::factory()->buyer()->create();
    BuyerCreditLimitRequest::factory()->approved()->create([
        'buyer_id' => $buyer->getKey(),
    ]);
    BuyerCreditLimitRequest::factory()->rejected()->create([
        'buyer_id' => $buyer->getKey(),
    ]);

    expect($buyer->pendingCreditLimitRequest())->toBeNull();
});

test('company derives available credit from limit when there is no exposure', function (): void {
    $buyer = Company::factory()->buyer()->create([
        'credit_limit' => '1500.75',
    ]);

    expect($buyer->derived_available_credit)->toBe(1500.75);
});

test('company casts requested credit limit as decimal', function (): void {
    $buyer = Company::factory()->buyer()->create([
        'requested_credit_limit' => '3000.50',
    ]);

    expect($buyer->requested_credit_limit)->toBe('3000.50');
});

test('company defaults derived available credit to zero', function (): void {
    $buyer = Company::factory()->buyer()->create([
        'credit_limit' => 0,
    ]);

    expect($buyer->derived_available_credit)->toBe(0.0);
});

test('company requested credit limit can be null', function (): void {
    $buyer = Company::factory()->buyer()->create([
        'requested_credit_limit' => null,
    ]);

    expect($buyer->requested_credit_limit)->toBeNull();
});
