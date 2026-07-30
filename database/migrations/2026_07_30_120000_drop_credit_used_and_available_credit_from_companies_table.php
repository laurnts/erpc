<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * credit_used and available_credit were hand-mutated running counters on
 * companies. A prior refactor replaced them with derived values computed
 * from confirmed buyer orders (Company::$credit_exposure and
 * Company::$derived_available_credit); the columns were kept only so
 * erp:reconcile-credit-exposure could compare stored against derived during
 * a transition period. That comparison period is over and both columns are
 * dead: nothing reads or writes them anymore. credit_limit is untouched — it
 * remains the buyer's actual limit and is still actively used.
 *
 * Production values were snapshotted to CSV before this migration ran.
 *
 * down() restores the columns with their original definition
 * (decimal(15,2) not null default 0, per the create_companies_table and
 * rename_active_credit_limit_to_available_credit migrations) but NOT their
 * values — nothing has maintained them since the derivation landed, so the
 * pre-drop CSV snapshot is the only remaining record of what they held.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['credit_used', 'available_credit']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->decimal('credit_used', 15, 2)->default(0);
            $table->decimal('available_credit', 15, 2)->default(0);
        });
    }
};
