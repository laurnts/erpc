<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A draft invoice carries no number; the counter is consumed at issue. That
 * makes a discarded draft cost nothing, so gaps arise only if the issue
 * transaction itself fails.
 *
 * The unique(team_id, invoice_number) index is deliberately kept: PostgreSQL
 * treats NULLs as distinct, so any number of unnumbered drafts coexist while
 * issued numbers stay unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_invoices', function (Blueprint $table): void {
            $table->string('invoice_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('buyer_invoices', function (Blueprint $table): void {
            $table->string('invoice_number')->nullable(false)->change();
        });
    }
};
