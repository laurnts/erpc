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
        // Add unit_of_measure_id columns to all tables that have unit fields
        $tables = [
            'articles',
            'request_items',
            'buyer_quote_items',
            'supplier_quote_items',
            'buyer_order_items',
            'supplier_order_items',
            'buyer_invoice_items',
            'supplier_invoice_items',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($table): void {
                    if (! Schema::hasColumn($table, 'unit_of_measure_id')) {
                        $tableBlueprint->foreignId('unit_of_measure_id')
                            ->nullable()
                            ->after('unit')
                            ->constrained('unit_of_measures')
                            ->nullOnDelete();

                        $tableBlueprint->index('unit_of_measure_id');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'articles',
            'request_items',
            'buyer_quote_items',
            'supplier_quote_items',
            'buyer_order_items',
            'supplier_order_items',
            'buyer_invoice_items',
            'supplier_invoice_items',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($table): void {
                    if (Schema::hasColumn($table, 'unit_of_measure_id')) {
                        $tableBlueprint->dropForeign([$table.'_unit_of_measure_id_foreign']);
                        $tableBlueprint->dropIndex([$table.'_unit_of_measure_id_index']);
                        $tableBlueprint->dropColumn('unit_of_measure_id');
                    }
                });
            }
        }
    }
};
