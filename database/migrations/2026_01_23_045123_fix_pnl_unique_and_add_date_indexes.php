<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_and_losses', function (Blueprint $table): void {
            // Drop the global unique constraint on pnl_number
            $table->dropUnique(['pnl_number']);

            // Add composite unique constraint on team_id + pnl_number
            $table->unique(['team_id', 'pnl_number']);

            // Add index on pnl_date for date-based queries
            $table->index('pnl_date');
        });

        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            // Add index on qe_date for date-based queries
            $table->index('qe_date');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            $table->dropIndex(['qe_date']);
        });

        Schema::table('profit_and_losses', function (Blueprint $table): void {
            $table->dropIndex(['pnl_date']);
            $table->dropUnique(['team_id', 'pnl_number']);
            $table->unique(['pnl_number']);
        });
    }
};
