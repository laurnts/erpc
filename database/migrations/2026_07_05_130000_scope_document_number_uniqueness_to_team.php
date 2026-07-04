<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Document numbers (PO/order/quote/invoice reference) are generated as
     * per-team sequences, but their unique constraints were global, so two
     * teams' first documents collided (both PO-2026-0001). Scope uniqueness
     * to (team_id, number), matching the buyer_invoices precedent.
     */
    public function up(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table): void {
            $table->dropUnique(['po_number']);
            $table->unique(['team_id', 'po_number']);
        });

        Schema::table('buyer_orders', function (Blueprint $table): void {
            $table->dropUnique(['order_number']);
            $table->unique(['team_id', 'order_number']);
        });

        Schema::table('buyer_quotes', function (Blueprint $table): void {
            $table->dropUnique(['quote_number']);
            $table->unique(['team_id', 'quote_number']);
        });

        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->dropUnique(['reference_number']);
            $table->unique(['team_id', 'reference_number']);
        });
    }

    public function down(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'po_number']);
            $table->unique(['po_number']);
        });

        Schema::table('buyer_orders', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'order_number']);
            $table->unique(['order_number']);
        });

        Schema::table('buyer_quotes', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'quote_number']);
            $table->unique(['quote_number']);
        });

        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'reference_number']);
            $table->unique(['reference_number']);
        });
    }
};
