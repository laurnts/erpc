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
};
