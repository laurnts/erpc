<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Backfill the default viewer role onto users created before the
     * every-user-has-a-role invariant existed. Uses the 'user' morph alias
     * and insertOrIgnore so re-running is a no-op.
     */
    public function up(): void
    {
        $roleId = DB::table('roles')
            ->where('name', 'viewer')
            ->where('guard_name', 'web')
            ->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'viewer',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')
            ->whereNotIn('id', DB::table('model_has_roles')->where('model_type', 'user')->select('model_id'))
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($roleId): void {
                DB::table('model_has_roles')->insertOrIgnore(
                    collect($users)->map(fn (object $user): array => [
                        'role_id' => $roleId,
                        'model_type' => 'user',
                        'model_id' => $user->id,
                    ])->all()
                );
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally left empty: removing a backfilled default role would
        // recreate the role-less users this migration exists to eliminate.
    }
};
