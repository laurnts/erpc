<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('profit_and_losses', function (Blueprint $table): void {
            // Frozen financial figures captured when the PNL is approved. When
            // non-null the P&L views/PDF read from it so an approved value never
            // changes, even if the source buyer quote is later revised.
            $table->json('financial_snapshot')->nullable()->after('data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profit_and_losses', function (Blueprint $table): void {
            $table->dropColumn('financial_snapshot');
        });
    }
};
