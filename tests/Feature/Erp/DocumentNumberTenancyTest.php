<?php

declare(strict_types=1);

use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;

/**
 * Document numbers (PO, order, quote, invoice reference) are sequenced per
 * team, so two teams' first documents legitimately share the same number.
 * The unique constraints must therefore be scoped to (team_id, number) —
 * matching the buyer_invoices precedent — or the second tenant's first
 * document fails with a global unique violation.
 */
function tenancyFixture(Team $team): array
{
    $user = User::factory()->withPersonalTeam()->create();
    $team->users()->attach($user, ['role' => 'admin']);
    $user->switchTeam($team);

    $buyer = Company::factory()->buyer()->recycle($team)->create();
    $supplier = Company::factory()->supplier()->recycle($team)->create();
    $request = Request::factory()->recycle($team)->recycle($buyer)->create();

    return [$user, $buyer, $supplier, $request];
}

it('allows two teams to hold the same generated document numbers', function (): void {
    Currency::factory()->create(['is_default' => true]);

    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    [, , $supplierA, $requestA] = tenancyFixture($teamA);
    [, , $supplierB, $requestB] = tenancyFixture($teamB);

    $orderA = SupplierOrder::factory()->recycle($teamA)->create([
        'request_id' => $requestA->getKey(),
        'supplier_id' => $supplierA->getKey(),
        'po_number' => null,
    ]);
    $orderB = SupplierOrder::factory()->recycle($teamB)->create([
        'request_id' => $requestB->getKey(),
        'supplier_id' => $supplierB->getKey(),
        'po_number' => null,
    ]);

    expect($orderA->po_number)->toBe($orderB->po_number)
        ->and($orderB->exists)->toBeTrue();
});

it('allows two teams to hold the same buyer order and quote numbers', function (): void {
    Currency::factory()->create(['is_default' => true]);

    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    [, $buyerA, , $requestA] = tenancyFixture($teamA);
    [, $buyerB, , $requestB] = tenancyFixture($teamB);

    $quoteA = BuyerQuote::factory()->recycle($teamA)->recycle($buyerA)->forRequest($requestA)->create(['quote_number' => null]);
    $quoteB = BuyerQuote::factory()->recycle($teamB)->recycle($buyerB)->forRequest($requestB)->create(['quote_number' => null]);

    expect($quoteA->quote_number)->toBe($quoteB->quote_number);

    $orderA = BuyerOrder::factory()->recycle($teamA)->recycle($buyerA)->create([
        'request_id' => $requestA->getKey(),
        'buyer_quote_id' => $quoteA->getKey(),
        'order_number' => null,
    ]);
    $orderB = BuyerOrder::factory()->recycle($teamB)->recycle($buyerB)->create([
        'request_id' => $requestB->getKey(),
        'buyer_quote_id' => $quoteB->getKey(),
        'order_number' => null,
    ]);

    expect($orderA->order_number)->toBe($orderB->order_number);
});

it('allows two teams to hold the same supplier quote number', function (): void {
    Currency::factory()->create(['is_default' => true]);

    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    [, , $supplierA, $requestA] = tenancyFixture($teamA);
    [, , $supplierB, $requestB] = tenancyFixture($teamB);

    $quoteA = SupplierQuote::factory()->recycle($teamA)->create([
        'request_id' => $requestA->getKey(),
        'supplier_id' => $supplierA->getKey(),
        'quote_number' => null,
    ]);
    $quoteB = SupplierQuote::factory()->recycle($teamB)->create([
        'request_id' => $requestB->getKey(),
        'supplier_id' => $supplierB->getKey(),
        'quote_number' => null,
    ]);

    expect($quoteA->quote_number)->toBe($quoteB->quote_number)
        ->and($quoteB->exists)->toBeTrue();
});
