<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outcome announcement stamp (per-quote): set by AnnounceRfqOutcomes on every
 * quote participating in the announced round. Any non-null value on a
 * request's quotes marks the evaluation round as closed (selection re-apply
 * locked), and drives the supplier-portal Won / "Not selected" rendering —
 * pre-announcement evaluation churn never leaks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->timestamp('outcomes_announced_at')->nullable()->after('sent_to_supplier_at');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->dropColumn('outcomes_announced_at');
        });
    }
};
