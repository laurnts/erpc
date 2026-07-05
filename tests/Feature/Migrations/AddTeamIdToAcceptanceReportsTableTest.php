<?php

declare(strict_types=1);

use App\Models\Request;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

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
