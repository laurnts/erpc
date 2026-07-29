<?php

declare(strict_types=1);

use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

it('keeps the existing quote number format', function (): void {
    $number = BuyerQuote::generateNextNumber($this->team->getKey());

    expect($number)->toMatch('/^BQ-\d{4}-\d{4}$/');
});

it('does not reissue a number after the 9999 boundary', function (): void {
    BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create(['quote_number' => 'BQ-'.date('Y').'-9999']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect(BuyerQuote::generateNextNumber($this->team->getKey()))
        ->toBe('BQ-'.date('Y').'-10000');
});

it('never issues the same number twice across many allocations', function (): void {
    $teamId = $this->team->getKey();

    $numbers = collect(range(1, 50))
        ->map(fn (): string => BuyerQuote::generateNextNumber($teamId))
        ->all();

    expect($numbers)->toHaveCount(50)
        ->and(array_unique($numbers))->toHaveCount(50);
});
