<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records whether an order debited buyer credit at confirmation. Previously this
 * was inferred by an EXISTS over buyer_credit_usage_histories, which cannot take
 * part in an aggregate exposure query cheaply.
 *
 * The backfill reproduces that same EXISTS once, so existing orders keep their
 * current classification.
 *
 * transaction_type IN ('debit', 'used'): current writers (BuyerOrder::confirm)
 * always stamp 'debit', but production data holds three confirmed orders whose
 * reservation row was stamped 'used' — an older code path for the same
 * confirmation event, verified present on the live database. Those orders are
 * still confirmed with credit_released = 0.00, i.e. still live exposure.
 * Matching only 'debit' silently drops them from the backfill and every
 * exposure query built on credit_reserved_at understates that buyer's
 * exposure (and overstates their available credit) with no error anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_orders', function (Blueprint $table): void {
            $table->timestamp('credit_reserved_at')->nullable()->after('credit_released');
            $table->index(['buyer_id', 'status', 'credit_reserved_at'], 'buyer_orders_credit_exposure_index');
        });

        // Every writer stamps the FQCN (App\Models\BuyerOrder); the bare
        // 'buyer_order' alias only ever appears in hand-seeded dev data. Both
        // forms must match or this backfill silently seeds nothing on a real
        // database and every historical order's exposure reads as zero.
        DB::statement(<<<'SQL'
            UPDATE buyer_orders
            SET credit_reserved_at = COALESCE(confirmed_at, created_at)
            WHERE EXISTS (
                SELECT 1
                FROM buyer_credit_usage_histories h
                WHERE h.related_type IN ('App\Models\BuyerOrder', 'buyer_order')
                  AND h.related_id = buyer_orders.id
                  AND h.transaction_type IN ('debit', 'used')
            )
        SQL);
    }

    public function down(): void
    {
        Schema::table('buyer_orders', function (Blueprint $table): void {
            $table->dropIndex('buyer_orders_credit_exposure_index');
            $table->dropColumn('credit_reserved_at');
        });
    }
};
