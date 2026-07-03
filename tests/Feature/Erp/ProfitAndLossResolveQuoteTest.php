<?php

declare(strict_types=1);

use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProfitAndLoss;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;

it('resolves the source buyer quote without writing to the database during render', function (): void {
    $team = Team::factory()->create();
    $user = User::factory()->recycle($team)->create();
    $this->actingAs($user);

    $buyer = Company::factory()->buyer()->recycle($team)->create();
    $currency = Currency::factory()->create();
    $request = Request::factory()->recycle($team)->recycle($buyer)->create();

    $quote = BuyerQuote::factory()
        ->recycle($team)
        ->recycle($buyer)
        ->forRequest($request)
        ->withCurrency($currency)
        ->create();

    $pnl = ProfitAndLoss::factory()->forRequest($request)->create(['buyer_quote_id' => null]);

    $resolved = $pnl->resolveSourceBuyerQuote();

    expect($resolved?->getKey())->toBe($quote->getKey())
        // The link is NOT persisted as a side effect of resolving (no write on render).
        ->and($pnl->fresh()->buyer_quote_id)->toBeNull();
});
