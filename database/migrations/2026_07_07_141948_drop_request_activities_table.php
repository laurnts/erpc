<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The dormant RequestActivity subsystem is removed in favor of the
     * Spatie-based activity log (App\Models\ActivityLog). No application
     * code ever wrote to request_activities, so the table is dropped.
     * Idempotent and safe to re-run.
     */
    public function up(): void
    {
        Schema::dropIfExists('request_activities');
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally irreversible: the RequestActivity capability is removed.
     */
    public function down(): void
    {
        //
    }
};
