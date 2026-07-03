<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Laravel\Jetstream\Features;

final class LocalSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            $this->command->info('Skipping local seeding as the environment is not local.');

            return;
        }

        $this->call(SystemAdministratorSeeder::class);

        $user = User::query()->firstOrCreate(
            ['email' => 'laurentius@aecs.id'],
            [
                'name' => 'Laurentius',
                'password' => bcrypt('Stfadmin24!'),
                'email_verified_at' => now(),
            ]
        );

        if (Features::hasTeamFeatures() && $user->personalTeam() === null) {
            $team = Team::factory()->create([
                'name' => $user->name.'\'s Team',
                'user_id' => $user->id,
                'personal_team' => true,
            ]);

            $user->ownedTeams()->save($team);
        }

        // Assign superadmin role for full ERP access
        if (! $user->hasRole('superadmin')) {
            $user->assignRole('superadmin');
        }

        $personalTeam = $user->personalTeam();

        if ($personalTeam === null) {
            return;
        }

        // Set current team to personal team
        $user->switchTeam($personalTeam);
    }
}
