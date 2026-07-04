<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('purges opportunity-typed residue in both morph forms and is idempotent', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    /** @var Team $team */
    $team = $user->personalTeam();

    $note = Note::factory()->for($team)->create();
    $task = Task::factory()->for($team)->create();

    DB::table('noteables')->insert([
        ['note_id' => $note->id, 'noteable_type' => 'opportunity', 'noteable_id' => 991],
        ['note_id' => $note->id, 'noteable_type' => 'App\Models\Opportunity', 'noteable_id' => 992],
    ]);
    DB::table('taskables')->insert([
        ['task_id' => $task->id, 'taskable_type' => 'opportunity', 'taskable_id' => 991],
    ]);
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
    $migration->up(); // idempotent

    expect(DB::table('noteables')->whereIn('noteable_type', ['opportunity', 'App\Models\Opportunity'])->count())->toBe(0)
        ->and(DB::table('taskables')->where('taskable_type', 'opportunity')->count())->toBe(0)
        ->and(DB::table('ai_summaries')->where('summarizable_type', 'opportunity')->count())->toBe(0)
        ->and(DB::table('custom_fields')->whereKey($fieldId)->count())->toBe(0)
        ->and(DB::table('custom_field_options')->where('custom_field_id', $fieldId)->count())->toBe(0)
        ->and(DB::table('custom_field_values')->where('custom_field_id', $fieldId)->count())->toBe(0)
        ->and(Schema::hasTable('opportunities'))->toBeFalse()
        ->and(Note::query()->whereKey($note->id)->exists())->toBeTrue()
        ->and(Task::query()->whereKey($task->id)->exists())->toBeTrue();
});
