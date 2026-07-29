<?php

declare(strict_types=1);

use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Services\Erp\Numbering\DocumentNumberAllocator;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
    $this->allocator = app(DocumentNumberAllocator::class);
});

it('seeds the counter above the highest existing number', function (): void {
    foreach (['BQ-2026-0007', 'BQ-2026-0042', 'BQ-2026-0009'] as $number) {
        BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->currency)
            ->for($this->request)
            ->create(['quote_number' => $number]);
    }

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(43);
});

it('is not fooled by lexical ordering past 9999', function (): void {
    foreach (['BQ-2026-9999', 'BQ-2026-10000'] as $number) {
        BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->currency)
            ->for($this->request)
            ->create(['quote_number' => $number]);
    }

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(10001);
});

it('counts soft-deleted documents so their numbers are never reissued', function (): void {
    $quote = BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create(['quote_number' => 'BQ-2026-0099']);
    $quote->delete();

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(100);
});

it('is idempotent and never lowers a counter', function (): void {
    BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create(['quote_number' => 'BQ-2026-0005']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();
    $this->allocator->next($this->team->getKey(), 'buyer_quote', '2026');
    $this->allocator->next($this->team->getKey(), 'buyer_quote', '2026');
    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(8);
});

it('changes nothing on a dry run', function (): void {
    BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create(['quote_number' => 'BQ-2026-0005']);

    $this->artisan('erp:backfill-document-sequences', ['--dry-run' => true])->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(1);
});
