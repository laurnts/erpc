<?php

declare(strict_types=1);

use App\Enums\CreditLimitRequestStatus;
use App\Models\BuyerCreditLimitRequest;
use App\Models\BuyerCreditLimitRequestApproval;
use App\Models\Company;
use App\Models\Team;
use App\Models\User;

test('buyer credit limit request belongs to team', function () {
    $team = Team::factory()->create();
    $request = BuyerCreditLimitRequest::factory()->create([
        'team_id' => $team->getKey(),
    ]);

    expect($request->team)->toBeInstanceOf(Team::class)
        ->and($request->team->getKey())->toBe($team->getKey());
});

test('buyer credit limit request belongs to buyer', function () {
    $buyer = Company::factory()->buyer()->create();
    $request = BuyerCreditLimitRequest::factory()->create([
        'buyer_id' => $buyer->getKey(),
    ]);

    expect($request->buyer)->toBeInstanceOf(Company::class)
        ->and($request->buyer->getKey())->toBe($buyer->getKey());
});

test('buyer credit limit request belongs to requested by user', function () {
    $user = User::factory()->create();
    $request = BuyerCreditLimitRequest::factory()->create([
        'requested_by_id' => $user->getKey(),
    ]);

    expect($request->requestedBy)->toBeInstanceOf(User::class)
        ->and($request->requestedBy->getKey())->toBe($user->getKey());
});

test('buyer credit limit request belongs to rejected by user', function () {
    $user = User::factory()->create();
    $request = BuyerCreditLimitRequest::factory()->rejected($user)->create();

    expect($request->rejectedBy)->toBeInstanceOf(User::class)
        ->and($request->rejectedBy->getKey())->toBe($user->getKey());
});

test('buyer credit limit request has many approvals', function () {
    $request = BuyerCreditLimitRequest::factory()->create();
    $approval1 = BuyerCreditLimitRequestApproval::factory()->create([
        'buyer_credit_limit_request_id' => $request->getKey(),
    ]);
    $approval2 = BuyerCreditLimitRequestApproval::factory()->create([
        'buyer_credit_limit_request_id' => $request->getKey(),
    ]);

    expect($request->approvals)->toHaveCount(2)
        ->and($request->approvals->first())->toBeInstanceOf(BuyerCreditLimitRequestApproval::class);
});

test('buyer credit limit request belongs to many approvers', function () {
    $request = BuyerCreditLimitRequest::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    BuyerCreditLimitRequestApproval::factory()->create([
        'buyer_credit_limit_request_id' => $request->getKey(),
        'user_id' => $user1->getKey(),
    ]);
    BuyerCreditLimitRequestApproval::factory()->create([
        'buyer_credit_limit_request_id' => $request->getKey(),
        'user_id' => $user2->getKey(),
    ]);

    expect($request->approvers)->toHaveCount(2)
        ->and($request->approvers->pluck('id')->toArray())->toContain($user1->getKey(), $user2->getKey());
});

test('buyer credit limit request defaults to pending status', function () {
    $request = BuyerCreditLimitRequest::factory()->create();

    expect($request->status)->toBe(CreditLimitRequestStatus::PENDING);
});

test('buyer credit limit request approval count returns correct number', function () {
    $request = BuyerCreditLimitRequest::factory()->create();
    
    expect($request->approvalCount())->toBe(0);

    BuyerCreditLimitRequestApproval::factory()->count(2)->create([
        'buyer_credit_limit_request_id' => $request->getKey(),
    ]);

    $request->refresh();
    expect($request->approvalCount())->toBe(2);
});

test('buyer credit limit request is approved when has 2 or more approvals', function () {
    $request = BuyerCreditLimitRequest::factory()->create();
    
    expect($request->isApproved())->toBeFalse();

    BuyerCreditLimitRequestApproval::factory()->count(1)->create([
        'buyer_credit_limit_request_id' => $request->getKey(),
    ]);
    $request->refresh();
    expect($request->isApproved())->toBeFalse();

    BuyerCreditLimitRequestApproval::factory()->create([
        'buyer_credit_limit_request_id' => $request->getKey(),
    ]);
    $request->refresh();
    expect($request->isApproved())->toBeTrue();
});

test('buyer credit limit request casts decimal fields correctly', function () {
    $request = BuyerCreditLimitRequest::factory()->create([
        'current_limit' => '1000.50',
        'requested_limit' => '2000.75',
    ]);

    expect($request->current_limit)->toBe('1000.50')
        ->and($request->requested_limit)->toBe('2000.75');
});

test('buyer credit limit request casts status enum correctly', function () {
    $request = BuyerCreditLimitRequest::factory()->approved()->create();

    expect($request->status)->toBeInstanceOf(CreditLimitRequestStatus::class)
        ->and($request->status)->toBe(CreditLimitRequestStatus::APPROVED);
});
