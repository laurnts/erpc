<?php

declare(strict_types=1);

use App\Models\AcceptanceReport;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Team;
use App\Support\RomanNumerals;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
});

it('keeps the QE number format', function (): void {
    $number = QuotationEvaluation::generateQeNumber($this->team->getKey());

    expect($number)->toBe(sprintf(
        '%03d-DS/QE/%s/%d', 1, RomanNumerals::month(now()->month), now()->year,
    ));
});

it('keeps the PNL number format', function (): void {
    $number = ProfitAndLoss::generatePnlNumber($this->team->getKey());

    expect($number)->toBe(sprintf(
        '%04d/EL-PNL/%s/%d', 1, RomanNumerals::month(now()->month), now()->year,
    ));
});

it('keeps the acceptance report format', function (): void {
    expect(AcceptanceReport::generateReportNumber($this->team->getKey()))
        ->toBe(sprintf('AR-%d-%04d', now()->year, 1));
});

it('never issues the same QE number twice across many allocations', function (): void {
    $teamId = $this->team->getKey();

    $numbers = collect(range(1, 30))
        ->map(fn (): string => QuotationEvaluation::generateQeNumber($teamId))
        ->all();

    expect(array_unique($numbers))->toHaveCount(30);
});

it('never issues the same PNL number twice across many allocations', function (): void {
    $teamId = $this->team->getKey();

    $numbers = collect(range(1, 30))
        ->map(fn (): string => ProfitAndLoss::generatePnlNumber($teamId))
        ->all();

    expect(array_unique($numbers))->toHaveCount(30);
});

it('never issues the same acceptance report number twice across many allocations', function (): void {
    $teamId = $this->team->getKey();

    $numbers = collect(range(1, 30))
        ->map(fn (): string => AcceptanceReport::generateReportNumber($teamId))
        ->all();

    expect(array_unique($numbers))->toHaveCount(30);
});
