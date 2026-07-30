<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three document-number columns were missed by
 * 2026_07_05_130000_scope_document_number_uniqueness_to_team: their unique
 * indexes were still global, so two teams issuing the same number (e.g. both
 * "SQ-2026-0001") would collide across tenants. Scope them to (team_id,
 * <x>_number), matching every other document-number column in the app.
 *
 * Verified safe against production before writing this migration: 1 team
 * exists today, so no cross-team collision is possible yet.
 * supplier_quotes had 80 rows / 80 distinct quote_numbers, shipments had
 * 17/17 distinct shipment_numbers, supplier_payments had 0 rows — and in
 * each table count(DISTINCT (team_id, number)) equalled count(DISTINCT
 * number), i.e. no duplicate numbers exist even ignoring team_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->dropUnique(['quote_number']);
            $table->unique(['team_id', 'quote_number']);
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique(['shipment_number']);
            $table->unique(['team_id', 'shipment_number']);
        });

        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->dropUnique(['payment_number']);
            $table->unique(['team_id', 'payment_number']);
        });
    }

    /**
     * Restores the global unique indexes. This will fail with a unique
     * violation if, since up() ran, two different teams have issued the same
     * number — which is exactly the situation up() makes possible. That
     * failure is correct: it means the global constraint can no longer be
     * satisfied, and rolling back would otherwise silently hide the
     * collision instead of surfacing it. Do not work around it here; resolve
     * the colliding numbers first.
     */
    public function down(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'quote_number']);
            $table->unique(['quote_number']);
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'shipment_number']);
            $table->unique(['shipment_number']);
        });

        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'payment_number']);
            $table->unique(['payment_number']);
        });
    }
};
