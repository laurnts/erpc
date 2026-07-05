<?php

declare(strict_types=1);

use App\Models\Request;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('backfills team_id from the parent request for pre-existing rows', function (): void {
    $team = Team::factory()->create();
    $request = Request::factory()->create(['team_id' => $team->id]);

    $migration = require database_path('migrations/2026_07_05_170000_add_team_id_to_acceptance_reports_table.php');
    $migration->down();

    $reportId = DB::table('acceptance_reports')->insertGetId([
        'request_id' => $request->id,
        'report_number' => 'AR-'.now()->year.'-0001',
        'reported_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    expect(DB::table('acceptance_reports')->where('id', $reportId)->value('team_id'))->toBe($team->id);
});

it('renumbers later duplicates when the per-request numbering collides within a team', function (): void {
    $team = Team::factory()->create();
    $requestA = Request::factory()->create(['team_id' => $team->id]);
    $requestB = Request::factory()->create(['team_id' => $team->id]);
    $year = now()->year;

    $migration = require database_path('migrations/2026_07_05_170000_add_team_id_to_acceptance_reports_table.php');
    $migration->down();

    // Under the old schema, numbering restarted per request, so two requests in
    // the same team could legitimately both hold AR-{year}-0001.
    $earlierId = DB::table('acceptance_reports')->insertGetId([
        'request_id' => $requestA->id,
        'report_number' => sprintf('AR-%d-0001', $year),
        'reported_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $laterId = DB::table('acceptance_reports')->insertGetId([
        'request_id' => $requestB->id,
        'report_number' => sprintf('AR-%d-0001', $year),
        'reported_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    $rows = DB::table('acceptance_reports')->orderBy('id')->pluck('report_number', 'id');

    expect($rows)->toHaveCount(2)
        ->and($rows[$earlierId])->toBe(sprintf('AR-%d-0001', $year))
        ->and($rows[$laterId])->toBe(sprintf('AR-%d-0002', $year));

    $uniqueIndexExists = collect(Schema::getIndexes('acceptance_reports'))
        ->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['team_id', 'report_number']);

    expect($uniqueIndexExists)->toBeTrue();
});
