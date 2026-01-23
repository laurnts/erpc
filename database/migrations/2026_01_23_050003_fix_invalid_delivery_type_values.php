<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix invalid delivery_type values
        // "Franco" is not a valid DeliveryType enum value
        // Map it to NULL or a valid value based on business logic
        // Valid values are: 'fob', 'cif', 'exw', 'ddp', 'dap'
        
        DB::table('companies')
            ->where('delivery_type', 'franco')
            ->orWhere('delivery_type', 'Franco')
            ->orWhere('delivery_type', 'FRANCO')
            ->update(['delivery_type' => null]);
        
        // Also fix delivery_type_details if it exists
        if (DB::getSchemaBuilder()->hasColumn('companies', 'delivery_type_details')) {
            DB::table('companies')
                ->where('delivery_type_details', 'franco')
                ->orWhere('delivery_type_details', 'Franco')
                ->orWhere('delivery_type_details', 'FRANCO')
                ->update(['delivery_type_details' => null]);
        }
        
        // Fix any other invalid enum values that might exist
        $validValues = ['fob', 'cif', 'exw', 'ddp', 'dap'];
        
        DB::table('companies')
            ->whereNotNull('delivery_type')
            ->whereNotIn('delivery_type', $validValues)
            ->update(['delivery_type' => null]);
        
        if (DB::getSchemaBuilder()->hasColumn('companies', 'delivery_type_details')) {
            DB::table('companies')
                ->whereNotNull('delivery_type_details')
                ->whereNotIn('delivery_type_details', $validValues)
                ->update(['delivery_type_details' => null]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse this migration as we don't know what the original values were
        // This is a data cleanup migration
    }
};
