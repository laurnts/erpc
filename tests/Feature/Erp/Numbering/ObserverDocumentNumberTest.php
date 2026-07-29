<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Request;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
});

it('assigns a distinct request number to every created request', function (): void {
    // request_number: null forces RequestObserver::generateRequestNumber() to
    // run; RequestFactory's definition otherwise fills in its own faker
    // number, which would pass this assertion without exercising the
    // allocator at all.
    $numbers = collect(range(1, 25))
        ->map(fn (): string => (string) Request::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->create(['request_number' => null])
            ->request_number)
        ->all();

    expect(array_unique($numbers))->toHaveCount(25);
});

it('does not reuse a soft-deleted request number', function (): void {
    $first = Request::factory()->recycle($this->team)->recycle($this->buyer)->create(['request_number' => null]);
    $firstNumber = (string) $first->request_number;
    $first->delete();

    $second = Request::factory()->recycle($this->team)->recycle($this->buyer)->create(['request_number' => null]);

    expect((string) $second->request_number)->not->toBe($firstNumber);
});

it('continues past the 9999 boundary', function (): void {
    Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create(['request_number' => 'REQ-'.date('Y').'-9999']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    $next = Request::factory()->recycle($this->team)->recycle($this->buyer)->create(['request_number' => null]);

    expect((string) $next->request_number)->toBe('REQ-'.date('Y').'-10000');
});
