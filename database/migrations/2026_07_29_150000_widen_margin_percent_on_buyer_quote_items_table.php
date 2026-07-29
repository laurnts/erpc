<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * buyer_quote_items.margin_percent was created as decimal(8, 4), capping it
     * at +/-9999.9999. MarginConvention::marginPercent() is unbounded below
     * whenever cost_price exceeds the net selling price (a real, if mistaken,
     * data entry case — e.g. cost keyed in rupiah against a price in
     * thousands), so a legitimately large negative margin overflows the
     * column on PostgreSQL. Widen it so the value is stored, not clamped or
     * hidden.
     */
    public function up(): void
    {
        Schema::table('buyer_quote_items', function (Blueprint $table): void {
            $table->decimal('margin_percent', 12, 4)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buyer_quote_items', function (Blueprint $table): void {
            $table->decimal('margin_percent', 8, 4)->default(0)->change();
        });
    }
};
