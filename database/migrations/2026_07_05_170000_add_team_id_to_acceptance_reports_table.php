<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acceptance reports were only scoped to their parent request, so report
     * numbers collided across teams. Add team_id (backfilled from the parent
     * request) and rescope the uniqueness constraint to (team_id, report_number).
     */
    public function up(): void
    {
        Schema::table('acceptance_reports', function (Blueprint $table): void {
            $table->unsignedBigInteger('team_id')->nullable()->after('id');
        });

        DB::statement('
            UPDATE acceptance_reports
            SET team_id = (
                SELECT team_id
                FROM requests
                WHERE requests.id = acceptance_reports.request_id
            )
        ');

        $this->renumberCollidingReports();

        Schema::table('acceptance_reports', function (Blueprint $table): void {
            $table->unsignedBigInteger('team_id')->nullable(false)->change();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->dropUnique(['request_id', 'report_number']);
            $table->unique(['team_id', 'report_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acceptance_reports', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'report_number']);
            $table->unique(['request_id', 'report_number']);
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });
    }

    /**
     * The old numbering restarted per request, so after the team_id backfill a
     * team can hold several rows with the same report_number. Keep the earliest
     * row (lowest id) of each colliding group and renumber the later ones to the
     * next free AR-{year}-{0-padded 4-digit} sequence for that team+year.
     */
    private function renumberCollidingReports(): void
    {
        $collisions = DB::table('acceptance_reports')
            ->select('team_id', 'report_number')
            ->groupBy('team_id', 'report_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($collisions as $collision) {
            $earliestRowId = DB::table('acceptance_reports')
                ->where('team_id', $collision->team_id)
                ->where('report_number', $collision->report_number)
                ->min('id');

            $laterRowIds = DB::table('acceptance_reports')
                ->where('team_id', $collision->team_id)
                ->where('report_number', $collision->report_number)
                ->where('id', '>', $earliestRowId)
                ->orderBy('id')
                ->pluck('id');

            $year = preg_match('/^AR-(\d{4})-\d+$/', (string) $collision->report_number, $matches) === 1
                ? (int) $matches[1]
                : (int) date('Y');

            foreach ($laterRowIds as $rowId) {
                DB::table('acceptance_reports')
                    ->where('id', $rowId)
                    ->update(['report_number' => $this->nextReportNumber((int) $collision->team_id, $year)]);
            }
        }
    }

    /**
     * Next free AR-{year}-{seq} for the team+year, based on the highest existing
     * sequence (recomputed per call so consecutive renumbers within one group
     * keep advancing).
     */
    private function nextReportNumber(int $teamId, int $year): string
    {
        $existingNumbers = DB::table('acceptance_reports')
            ->where('team_id', $teamId)
            ->where('report_number', 'like', sprintf('AR-%d-%%', $year))
            ->pluck('report_number');

        $maxSequence = 0;
        foreach ($existingNumbers as $reportNumber) {
            if (preg_match('/^AR-'.$year.'-(\d+)$/', (string) $reportNumber, $matches) === 1) {
                $maxSequence = max($maxSequence, (int) $matches[1]);
            }
        }

        return sprintf('AR-%d-%04d', $year, $maxSequence + 1);
    }
};
