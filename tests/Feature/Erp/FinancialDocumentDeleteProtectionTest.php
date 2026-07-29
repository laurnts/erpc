<?php

declare(strict_types=1);

use App\Models\BuyerInvoice;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

it('refuses to hard-delete a request that has a buyer invoice', function (): void {
    BuyerInvoice::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create();

    expect(fn (): mixed => Request::withTrashed()
        ->whereKey($this->request->getKey())
        ->forceDelete())
        ->toThrow(QueryException::class);

    expect(Request::withTrashed()->whereKey($this->request->getKey())->exists())->toBeTrue();
});

it('refuses to hard-delete a company that has a buyer invoice through its request', function (): void {
    BuyerInvoice::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create();

    expect(fn (): mixed => Company::withTrashed()
        ->whereKey($this->buyer->getKey())
        ->forceDelete())
        ->toThrow(QueryException::class);
});

it('still allows hard-deleting a request with no financial documents', function (): void {
    $orphan = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();

    Request::withTrashed()->whereKey($orphan->getKey())->forceDelete();

    expect(Request::withTrashed()->whereKey($orphan->getKey())->exists())->toBeFalse();
});
