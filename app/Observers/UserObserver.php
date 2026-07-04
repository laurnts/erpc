<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use Spatie\Permission\Models\Role;

final readonly class UserObserver
{
    /**
     * Handle the User "created" event.
     *
     * Every user carries at least the read-only viewer role so authorization
     * never depends on an unassigned-role edge case. Staff panel access is
     * still gated by team membership in canAccessPanel(), so the default role
     * is inert for portal-only users. The role is created on the fly so user
     * creation never depends on the permission seeder having run; the seeder
     * syncs the actual permissions onto it by name.
     */
    public function created(User $user): void
    {
        $role = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        $user->assignRole($role);
    }
}
