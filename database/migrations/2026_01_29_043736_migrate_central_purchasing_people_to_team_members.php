<?php

declare(strict_types=1);

use App\Models\People;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration maps existing Central Purchasing People records to team members (Users).
     * For each People record with is_central_purchasing = true:
     * 1. Find or create corresponding User (by email or name matching)
     * 2. Add User to team as Central Purchasing member with appropriate sub-role
     * 3. Update foreign key references in QE/PNL documents from People ID to User ID
     */
    public function up(): void
    {
        // Check if columns still exist (they may have been removed by a later migration)
        if (! Schema::hasColumn('people', 'is_central_purchasing') ||
            ! Schema::hasColumn('people', 'central_purchasing_role')) {
            // Columns have already been removed, migration likely already ran or data was migrated
            \Log::info('Central Purchasing columns already removed from people table. Skipping migration.');

            return;
        }

        // Get all Central Purchasing People records
        $centralPurchasingPeople = DB::table('people')
            ->where('is_central_purchasing', true)
            ->whereNotNull('central_purchasing_role')
            ->get();

        foreach ($centralPurchasingPeople as $person) {
            $team = Team::find($person->team_id);
            if (! $team) {
                continue;
            }

            // Try to find existing User by email (from custom fields) or name
            $user = $this->findOrCreateUserForPeople($person, $team);

            if (! $user instanceof \App\Models\User) {
                // Skip if we can't create/find a user
                \Log::warning("Could not migrate Central Purchasing People ID {$person->id} to team member");

                continue;
            }

            // Add user to team with Central Purchasing role if not already a member
            $existingMembership = DB::table('team_user')
                ->where('team_id', $team->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $existingMembership) {
                DB::table('team_user')->insert([
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'role' => 'central_purchasing',
                    'central_purchasing_role' => $person->central_purchasing_role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Update existing membership if needed
                DB::table('team_user')
                    ->where('team_id', $team->id)
                    ->where('user_id', $user->id)
                    ->update([
                        'role' => 'central_purchasing',
                        'central_purchasing_role' => $person->central_purchasing_role,
                        'updated_at' => now(),
                    ]);
            }

            // Update foreign key references in quotation_evaluations
            $this->updateForeignKeys('quotation_evaluations', $person->id, $user->id);

            // Update foreign key references in profit_and_losses
            $this->updateForeignKeys('profit_and_losses', $person->id, $user->id);
        }
    }

    /**
     * Find or create a User for a People record.
     */
    private function findOrCreateUserForPeople(object $person, Team $team): ?User
    {
        // Try to get email from custom fields (if custom fields table exists)
        $email = null;
        try {
            $customField = DB::table('custom_field_values')
                ->where('model_type', 'people')
                ->where('model_id', $person->id)
                ->where('field_key', 'email')
                ->first();

            if ($customField) {
                $email = $customField->value;
            }
        } catch (\Exception) {
            // Custom fields table might not exist or have different structure
        }

        // Try to find User by email
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                return $user;
            }
        }

        // Try to find User by name (less reliable)
        $user = User::where('name', $person->name)->first();
        if ($user) {
            return $user;
        }

        // Create new User
        $email = $email ?: strtolower(str_replace(' ', '.', $person->name)).'@'.str_replace(' ', '', strtolower($team->name)).'.local';

        // Ensure email is unique
        $baseEmail = $email;
        $counter = 1;
        while (User::where('email', $email)->exists()) {
            $email = str_replace('@', $counter.'@', $baseEmail);
            $counter++;
        }

        return User::create([
            'name' => $person->name,
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)), // Temporary password
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Update foreign key references from People ID to User ID.
     */
    private function updateForeignKeys(string $table, int $peopleId, int $userId): void
    {
        $fields = ['prepared_by_id', 'dept_head_sales_id', 'deputy_director_id', 'approved_by_id'];

        foreach ($fields as $field) {
            DB::table($table)
                ->where($field, $peopleId)
                ->update([$field => $userId]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: This is a destructive operation. Data migration reversal is complex
     * and may not perfectly restore the original state.
     */
    public function down(): void
    {
        // Note: Reversing this migration is complex and may cause data loss
        // We'll log a warning but not attempt full reversal
        \Log::warning('Reversing Central Purchasing People to Team Members migration is not fully supported');
    }
};
