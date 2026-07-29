<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('purges opportunity-typed residue in both morph forms and is idempotent', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    /** @var Team $team */
    $team = $user->personalTeam();

    DB::table('ai_summaries')->insert([
        'team_id' => $team->id,
        'summarizable_type' => 'opportunity',
        'summarizable_id' => 991,
        'summary' => 'stale opportunity summary',
        'model_used' => 'test',
        'prompt_tokens' => 1,
        'completion_tokens' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $fieldId = DB::table('custom_fields')->insertGetId([
        'tenant_id' => $team->id,
        'code' => 'stage',
        'name' => 'Stage',
        'type' => 'select',
        'entity_type' => 'App\Models\Opportunity',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('custom_field_options')->insert([
        'custom_field_id' => $fieldId,
        'name' => 'Won',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('custom_field_values')->insert([
        'tenant_id' => $team->id,
        'custom_field_id' => $fieldId,
        'entity_type' => 'opportunity',
        'entity_id' => 991,
    ]);

    $migration = require database_path('migrations/2026_07_04_094354_remove_opportunities_entity.php');
    $migration->up();
    $migration->up(); // idempotent, and survives the dropped noteables/taskables pivots

    expect(DB::table('ai_summaries')->where('summarizable_type', 'opportunity')->count())->toBe(0)
        ->and(DB::table('custom_fields')->where('id', $fieldId)->count())->toBe(0)
        ->and(DB::table('custom_field_options')->where('custom_field_id', $fieldId)->count())->toBe(0)
        ->and(DB::table('custom_field_values')->where('custom_field_id', $fieldId)->count())->toBe(0)
        ->and(Schema::hasTable('opportunities'))->toBeFalse();
});

it('drops the task and note tables and purges their custom-field residue', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    /** @var Team $team */
    $team = $user->personalTeam();

    DB::table('ai_summaries')->insert([
        'team_id' => $team->id,
        'summarizable_type' => 'task',
        'summarizable_id' => 991,
        'summary' => 'stale task summary',
        'model_used' => 'test',
        'prompt_tokens' => 1,
        'completion_tokens' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $fieldId = DB::table('custom_fields')->insertGetId([
        'tenant_id' => $team->id,
        'code' => 'status',
        'name' => 'Status',
        'type' => 'select',
        'entity_type' => 'App\Models\Task',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('custom_field_values')->insert([
        'tenant_id' => $team->id,
        'custom_field_id' => $fieldId,
        'entity_type' => 'task',
        'entity_id' => 991,
    ]);

    $migration = require database_path('migrations/2026_07_04_113447_remove_tasks_and_notes_entities.php');
    $migration->up();
    $migration->up(); // idempotent

    expect(DB::table('ai_summaries')->whereIn('summarizable_type', ['task', 'note'])->count())->toBe(0)
        ->and(DB::table('custom_fields')->where('id', $fieldId)->count())->toBe(0)
        ->and(DB::table('custom_field_values')->where('custom_field_id', $fieldId)->count())->toBe(0)
        ->and(Schema::hasTable('tasks'))->toBeFalse()
        ->and(Schema::hasTable('notes'))->toBeFalse()
        ->and(Schema::hasTable('taskables'))->toBeFalse()
        ->and(Schema::hasTable('noteables'))->toBeFalse()
        ->and(Schema::hasTable('task_user'))->toBeFalse();
});
