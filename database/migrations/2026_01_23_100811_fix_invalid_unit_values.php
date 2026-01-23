<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix invalid unit values in the database.
     * "orang" (Indonesian for "piece/unit") is mapped to "pcs" (pieces).
     */
    public function up(): void
    {
        $tables = [
            'buyer_quote_items',
            'buyer_order_items',
            'supplier_quote_items',
            'supplier_order_items',
            'request_items',
            'articles',
        ];

        foreach ($tables as $table) {
            // Map "orang" to "pcs" (pieces)
            DB::table($table)
                ->where('unit', 'orang')
                ->update(['unit' => 'pcs']);

            // Set any other invalid unit values to 'pcs' (default value)
            // Valid values: pcs, kg, mt, set, box, roll, pair, l, m
            $validUnits = ['pcs', 'kg', 'mt', 'set', 'box', 'roll', 'pair', 'l', 'm'];
            
            DB::table($table)
                ->whereNotNull('unit')
                ->whereNotIn('unit', $validUnits)
                ->update(['unit' => 'pcs']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed - we're fixing invalid data
    }
};
