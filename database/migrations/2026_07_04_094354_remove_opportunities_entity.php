<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> Morph alias and FQCN forms found in polymorphic type columns */
    private array $opportunityTypes = ['opportunity', 'App\Models\Opportunity'];

    /**
     * Run the migrations.
     *
     * The Opportunity entity (CRM deals pipeline) is removed; the ERP
     * Request -> Quote -> Order workflow covers deal tracking. Purges all
     * opportunity-typed polymorphic rows, custom-field storage, and AI
     * summaries, then drops the table. Idempotent and safe to re-run.
     */
    public function up(): void
    {
        DB::table('noteables')->whereIn('noteable_type', $this->opportunityTypes)->delete();
        DB::table('taskables')->whereIn('taskable_type', $this->opportunityTypes)->delete();
        DB::table('ai_summaries')->whereIn('summarizable_type', $this->opportunityTypes)->delete();

        $customFieldIds = DB::table('custom_fields')
            ->whereIn('entity_type', $this->opportunityTypes)
            ->pluck('id');

        DB::table('custom_field_values')
            ->where(fn ($query) => $query
                ->whereIn('entity_type', $this->opportunityTypes)
                ->orWhereIn('custom_field_id', $customFieldIds))
            ->delete();
        DB::table('custom_field_options')->whereIn('custom_field_id', $customFieldIds)->delete();
        DB::table('custom_fields')->whereIn('id', $customFieldIds)->delete();
        DB::table('custom_field_sections')->whereIn('entity_type', $this->opportunityTypes)->delete();

        Schema::dropIfExists('opportunities');
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally irreversible: the Opportunity capability is removed.
     */
    public function down(): void
    {
        //
    }
};
