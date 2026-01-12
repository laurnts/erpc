<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Relaticle\SystemAdmin\Enums\SystemAdministratorRole;
use Relaticle\SystemAdmin\Models\SystemAdministrator;

final class SystemAdministratorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SystemAdministrator::firstOrCreate(
            ['email' => 'laurentius@aecs.id'],
            [
                'name' => 'System Administrator',
                'password' => bcrypt('Stfadmin24!'),
                'role' => SystemAdministratorRole::SuperAdministrator,
                'email_verified_at' => now(),
            ]
        );
    }
}
