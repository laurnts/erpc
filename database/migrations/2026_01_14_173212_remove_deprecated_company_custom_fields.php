<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get IDs of custom fields to delete
        $fieldIds = DB::table('custom_fields')
            ->where('entity_type', 'App\\Models\\Company')
            ->whereIn('code', ['domain_name', 'linkedin', 'icp'])
            ->pluck('id');

        if ($fieldIds->isNotEmpty()) {
            // Delete custom field values first (foreign key constraint)
            DB::table('custom_field_values')
                ->whereIn('custom_field_id', $fieldIds)
                ->delete();

            // Delete the custom fields
            DB::table('custom_fields')
                ->whereIn('id', $fieldIds)
                ->delete();
        }

        // Also remove LinkedIn from People custom fields
        $peopleLinkedinIds = DB::table('custom_fields')
            ->where('entity_type', 'App\\Models\\People')
            ->where('code', 'linkedin')
            ->pluck('id');

        if ($peopleLinkedinIds->isNotEmpty()) {
            DB::table('custom_field_values')
                ->whereIn('custom_field_id', $peopleLinkedinIds)
                ->delete();

            DB::table('custom_fields')
                ->whereIn('id', $peopleLinkedinIds)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot restore deleted data
    }
};
