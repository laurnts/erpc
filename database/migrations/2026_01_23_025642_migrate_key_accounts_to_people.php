<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get custom field IDs for People
        $emailsFieldId = DB::table('custom_fields')
            ->where('entity_type', \App\Models\People::class)
            ->where('code', 'emails')
            ->value('id');

        $phoneFieldId = DB::table('custom_fields')
            ->where('entity_type', \App\Models\People::class)
            ->where('code', 'phone_number')
            ->value('id');

        // Migrate key_accounts to people
        $keyAccounts = DB::table('key_accounts')->get();

        foreach ($keyAccounts as $keyAccount) {
            // Insert into people table
            $peopleId = DB::table('people')->insertGetId([
                'team_id' => $keyAccount->team_id,
                'creator_id' => $keyAccount->creator_id,
                'name' => $keyAccount->name,
                'is_key_account' => true,
                'creation_source' => 'web', // Default value for migrated records
                'created_at' => $keyAccount->created_at,
                'updated_at' => $keyAccount->updated_at,
            ]);

            // Get custom field IDs for this team (tenant-aware)
            $teamEmailsFieldId = DB::table('custom_fields')
                ->where('entity_type', \App\Models\People::class)
                ->where('code', 'emails')
                ->where('tenant_id', $keyAccount->team_id)
                ->value('id') ?? $emailsFieldId;

            $teamPhoneFieldId = DB::table('custom_fields')
                ->where('entity_type', \App\Models\People::class)
                ->where('code', 'phone_number')
                ->where('tenant_id', $keyAccount->team_id)
                ->value('id') ?? $phoneFieldId;

            // Migrate email to custom fields (emails is tags input, stored as JSON array)
            if ($keyAccount->email && $teamEmailsFieldId) {
                $emailInsert = [
                    'entity_type' => \App\Models\People::class,
                    'entity_id' => $peopleId,
                    'custom_field_id' => $teamEmailsFieldId,
                    'json_value' => json_encode([$keyAccount->email]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Add tenant_id if multi-tenancy is enabled
                if (DB::getSchemaBuilder()->hasColumn('custom_field_values', 'tenant_id')) {
                    $emailInsert['tenant_id'] = $keyAccount->team_id;
                }

                DB::table('custom_field_values')->insert($emailInsert);
            }

            // Migrate phone to custom fields (phone_number is text, stored as string_value)
            if ($keyAccount->phone && $teamPhoneFieldId) {
                $phoneInsert = [
                    'entity_type' => \App\Models\People::class,
                    'entity_id' => $peopleId,
                    'custom_field_id' => $teamPhoneFieldId,
                    'string_value' => $keyAccount->phone,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Add tenant_id if multi-tenancy is enabled
                if (DB::getSchemaBuilder()->hasColumn('custom_field_values', 'tenant_id')) {
                    $phoneInsert['tenant_id'] = $keyAccount->team_id;
                }

                DB::table('custom_field_values')->insert($phoneInsert);
            }

            // Store mapping for foreign key updates
            DB::table('key_account_people_mapping')->insert([
                'key_account_id' => $keyAccount->id,
                'people_id' => $peopleId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get mapping and restore key_accounts
        $mappings = DB::table('key_account_people_mapping')->get();

        foreach ($mappings as $mapping) {
            $person = DB::table('people')->find($mapping->people_id);

            if ($person) {
                // Get custom field values
                $emailsFieldId = DB::table('custom_fields')
                    ->where('entity_type', \App\Models\People::class)
                    ->where('code', 'emails')
                    ->value('id');

                $phoneFieldId = DB::table('custom_fields')
                    ->where('entity_type', \App\Models\People::class)
                    ->where('code', 'phone_number')
                    ->value('id');

                $emailValue = null;
                if ($emailsFieldId) {
                    $emailData = DB::table('custom_field_values')
                        ->where('entity_type', \App\Models\People::class)
                        ->where('entity_id', $mapping->people_id)
                        ->where('custom_field_id', $emailsFieldId)
                        ->value('json_value');
                    if ($emailData) {
                        $emails = json_decode((string) $emailData, true);
                        $emailValue = $emails[0] ?? null;
                    }
                }

                $phoneValue = null;
                if ($phoneFieldId) {
                    $phoneValue = DB::table('custom_field_values')
                        ->where('entity_type', \App\Models\People::class)
                        ->where('entity_id', $mapping->people_id)
                        ->where('custom_field_id', $phoneFieldId)
                        ->value('string_value');
                }

                // Restore key_account
                DB::table('key_accounts')->insert([
                    'id' => $mapping->key_account_id,
                    'team_id' => $person->team_id,
                    'creator_id' => $person->creator_id,
                    'name' => $person->name,
                    'email' => $emailValue,
                    'phone' => $phoneValue,
                    'is_active' => $person->deleted_at === null, // Use soft delete status
                    'created_at' => $person->created_at,
                    'updated_at' => $person->updated_at,
                ]);
            }
        }

        // Delete migrated people records
        $peopleIds = DB::table('key_account_people_mapping')->pluck('people_id');
        DB::table('people')->whereIn('id', $peopleIds)->delete();

        // Drop mapping table
        DB::table('key_account_people_mapping')->truncate();
    }
};
