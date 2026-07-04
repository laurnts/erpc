<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> Morph alias and FQCN forms found in polymorphic type columns */
    private array $removedTypes = ['task', 'App\Models\Task', 'note', 'App\Models\Note'];

    /**
     * Run the migrations.
     *
     * The Task and Note entities (the last CRM activity records) are removed;
     * the ERP workflow tracks work through Requests, Quotes, and Orders.
     * Purges all task/note-typed custom-field storage and AI summaries, then
     * drops the tables and their pivots. Idempotent and safe to re-run.
     */
    public function up(): void
    {
        DB::table('ai_summaries')->whereIn('summarizable_type', $this->removedTypes)->delete();

        $customFieldIds = DB::table('custom_fields')
            ->whereIn('entity_type', $this->removedTypes)
            ->pluck('id');

        DB::table('custom_field_values')
            ->where(fn ($query) => $query
                ->whereIn('entity_type', $this->removedTypes)
                ->orWhereIn('custom_field_id', $customFieldIds))
            ->delete();
        DB::table('custom_field_options')->whereIn('custom_field_id', $customFieldIds)->delete();
        DB::table('custom_fields')->whereIn('id', $customFieldIds)->delete();
        DB::table('custom_field_sections')->whereIn('entity_type', $this->removedTypes)->delete();

        Schema::dropIfExists('taskables');
        Schema::dropIfExists('noteables');
        Schema::dropIfExists('task_user');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('notes');
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally irreversible: the Task and Note capabilities are removed.
     */
    public function down(): void
    {
        //
    }
};
