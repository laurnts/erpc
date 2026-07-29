<?php

declare(strict_types=1);

use App\Models\BuyerInvoice;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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

    // PostgreSQL aborts the enclosing transaction after a RESTRICT violation,
    // so the follow-up exists() query would fail with "current transaction is
    // aborted" unless the delete runs in its own savepoint. DB::transaction()
    // nests as a savepoint under RefreshDatabase's outer test transaction, and
    // rolls back only that savepoint when the callback throws, leaving the
    // outer transaction (and this test's later assertions) intact.
    expect(fn (): mixed => DB::transaction(fn (): mixed => Request::withTrashed()
        ->whereKey($this->request->getKey())
        ->forceDelete()))
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
