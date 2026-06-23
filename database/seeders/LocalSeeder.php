<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\People;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Laravel\Jetstream\Features;
use Relaticle\CustomFields\Models\CustomField;

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

        $teamId = $personalTeam->id;
        //
        //        User::factory()
        //            ->withPersonalTeam()
        //            ->create([
        //                'name' => 'Test User',
        //                'email' => 'test@example.com',
        //            ]);
        //
        //        // Create 10 Test Users
        User::factory()
            ->count(10)
            ->afterCreating(function (User $user) use ($teamId): void {
                // Assign the user to the personal team.
                $user->teams()->attach($teamId, [
                    'role' => 'member',
                ]);
            })
            ->create();
        //
        //        // Set the current user and tenant.
        //        Auth::setUser($user);
        //        Filament::setTenant($user->personalTeam());
        //
        //        $customFields = CustomField::query()
        //            ->whereIn('code', ['icp', 'stage', 'domain_name'])
        //            ->get()
        //            ->keyBy('code');
        //
        //        Company::factory()
        //            ->for($user->personalTeam(), 'team')
        //            ->count(50)
        //            ->afterCreating(function (Company $company) use ($customFields): void {
        //                $company->saveCustomFieldValue($customFields->get('domain_name'), 'https://'.fake()->domainName());
        //                $company->saveCustomFieldValue($customFields->get('icp'), fake()->boolean(70));
        //            })
        //            ->create();
        //
        //        // Create people.
        //        People::factory()
        //            ->for($user->personalTeam(), 'team')
        //            ->for($user->currentTeam->companies->random(), 'company')
        //            ->state(new Sequence(
        //                fn (Sequence $sequence): array => ['company_id' => $user->personalTeam()->companies->random()->id]
        //            ))
        //            ->count(500)->create();
        //
        //        // Create opportunities.
        //        Opportunity::factory()->for($user->personalTeam(), 'team')
        //            ->count(150)
        //            ->afterCreating(function (Opportunity $opportunity) use ($customFields): void {
        //                $opportunity->saveCustomFieldValue($customFields->get('stage'), $customFields->get('stage')->options->random()->id);
        //            })
        //            ->create();
    }
}
