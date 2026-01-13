<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds notification_metadata column to ERP tables for tracking notification state.
 *
 * This column is used by alert jobs to track which notifications have been sent
 * to prevent duplicate notifications (e.g., quote expiration alerts, overdue invoice alerts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_quotes', function (Blueprint $table): void {
            $table->json('notification_metadata')->nullable()->after('internal_notes');
        });

        Schema::table('buyer_invoices', function (Blueprint $table): void {
            $table->json('notification_metadata')->nullable()->after('notes');
        });

        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->json('notification_metadata')->nullable()->after('internal_notes');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_quotes', function (Blueprint $table): void {
            $table->dropColumn('notification_metadata');
        });

        Schema::table('buyer_invoices', function (Blueprint $table): void {
            $table->dropColumn('notification_metadata');
        });

        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->dropColumn('notification_metadata');
        });
    }
};
