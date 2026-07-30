<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Request;
use App\Models\Shipment;
use App\Models\Team;
use App\Support\RomanNumerals;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

it('keeps the existing DO number format', function (): void {
    $shipment = Shipment::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->create();

    $doNumber = $shipment->generateDoNumber();

    expect($doNumber)->toMatch('#^\d{4}-CP/DO/[IVX]+/\d{4}$#')
        ->and($shipment->do_number)->toBe($doNumber);
});

it('issues distinct, incrementing DO numbers within one team and month', function (): void {
    $numbers = collect(range(1, 10))
        ->map(function () {
            $shipment = Shipment::factory()
                ->recycle($this->team)
                ->recycle($this->request)
                ->create();

            return $shipment->generateDoNumber();
        })
        ->all();

    expect(array_unique($numbers))->toHaveCount(10);

    $sequences = collect($numbers)
        ->map(fn (string $number): int => (int) explode('-', $number)[0])
        ->sort()
        ->values()
        ->all();

    expect($sequences)->toBe(range(1, 10));
});

it('continues past the 9999 boundary after backfilling', function (): void {
    $month = (int) now()->format('n');
    $year = (int) now()->format('Y');
    $romanMonth = RomanNumerals::month($month);

    Shipment::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->create(['do_number' => sprintf('9999-CP/DO/%s/%d', $romanMonth, $year)]);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    $shipment = Shipment::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->create();

    expect($shipment->generateDoNumber())->toBe(sprintf('10000-CP/DO/%s/%d', $romanMonth, $year));
});

it('scopes the counter per month, so one period does not advance another', function (): void {
    $shipmentA = Shipment::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->create();
    $shipmentB = Shipment::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->create();

    $currentMonth = (int) now()->format('n');
    $otherMonth = $currentMonth === 1 ? 2 : 1;
    $year = (int) now()->format('Y');

    // Simulate a shipment already numbered in a different month/period by
    // seeding the counter directly, then confirm the current month's
    // allocation still starts fresh at 1.
    app(\App\Services\Erp\Numbering\DocumentNumberAllocator::class)->seed(
        $this->team->getKey(),
        'shipment_do',
        RomanNumerals::month($otherMonth).'/'.$year,
        50,
    );

    $doNumberA = $shipmentA->generateDoNumber();

    expect($doNumberA)->toStartWith('0001-');

    $doNumberB = $shipmentB->generateDoNumber();

    expect($doNumberB)->toStartWith('0002-');
});
