<?php

declare(strict_types=1);

use App\Models\AcceptanceReport;
use App\Models\Request;
use App\Models\Team;

it('assigns team_id from the parent request when creating', function (): void {
    $team = Team::factory()->create();
    $request = Request::factory()->create(['team_id' => $team->id]);

    $report = AcceptanceReport::factory()->create(['request_id' => $request->id]);

    expect($report->team_id)->toBe($team->id);
});

it('does not override an explicitly assigned team_id', function (): void {
    $requestTeam = Team::factory()->create();
    $explicitTeam = Team::factory()->create();
    $request = Request::factory()->create(['team_id' => $requestTeam->id]);

    $report = AcceptanceReport::factory()->create([
        'request_id' => $request->id,
        'team_id' => $explicitTeam->id,
    ]);

    expect($report->team_id)->toBe($explicitTeam->id);
});

it('scopes generated report numbers per team so two teams can both start at 0001', function (): void {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $requestA = Request::factory()->create(['team_id' => $teamA->id]);
    $requestB = Request::factory()->create(['team_id' => $teamB->id]);

    $reportA = AcceptanceReport::factory()->create(['request_id' => $requestA->id]);
    $reportB = AcceptanceReport::factory()->create(['request_id' => $requestB->id]);

    $year = now()->year;
    expect($reportA->report_number)->toBe(sprintf('AR-%d-0001', $year))
        ->and($reportB->report_number)->toBe(sprintf('AR-%d-0001', $year));

    $secondReportForTeamA = AcceptanceReport::factory()->create(['request_id' => $requestA->id]);
    expect($secondReportForTeamA->report_number)->toBe(sprintf('AR-%d-0002', $year));
});

it('generates the next number from the highest existing sequence, not the last-inserted row', function (): void {
    $team = Team::factory()->create();
    $request = Request::factory()->create(['team_id' => $team->id]);
    $year = now()->year;

    $first = AcceptanceReport::factory()->create(['request_id' => $request->id]);
    $second = AcceptanceReport::factory()->create(['request_id' => $request->id]);

    // Simulate a historical/imported number that is higher than the most recently
    // inserted row's number. A naive "last row by id" implementation would only
    // look at $second and produce 0003, colliding with a future 0050 import.
    $first->forceFill(['report_number' => sprintf('AR-%d-0050', $year)])->save();
    expect($second->report_number)->toBe(sprintf('AR-%d-0002', $year));

    $third = AcceptanceReport::factory()->create(['request_id' => $request->id]);

    expect($third->report_number)->toBe(sprintf('AR-%d-0051', $year))
        ->and($first->fresh()->report_number)->toBe(sprintf('AR-%d-0050', $year));
});

it('persists an attachment media row on the local disk', function (): void {
    $team = Team::factory()->create();
    $request = Request::factory()->create(['team_id' => $team->id]);
    $report = AcceptanceReport::factory()->create(['request_id' => $request->id]);

    $path = tempnam(sys_get_temp_dir(), 'acceptance-report-attachment');
    file_put_contents($path, "%PDF-1.4\n%%EOF");
    rename($path, $path.'.pdf');
    $path .= '.pdf';

    $media = $report->addMedia($path)->toMediaCollection('attachments');

    expect($report->media()->count())->toBe(1)
        ->and($media->disk)->toBe('local')
        ->and($media->model_type)->toBe('acceptance_report');
});
