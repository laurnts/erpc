<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_people', function (Blueprint $table): void {
            // Change role column to enum type
            // MySQL doesn't support native enum types well, so we'll use string with check constraint
            // PostgreSQL supports enum types natively
            $driver = DB::getDriverName();

            if ($driver === 'pgsql') {
                // Create enum type if it doesn't exist
                DB::statement("
                    DO $$ BEGIN
                        CREATE TYPE contact_role AS ENUM ('primary', 'billing', 'technical', 'sales', 'support', 'other');
                    EXCEPTION
                        WHEN duplicate_object THEN null;
                    END $$;
                ");

                // Change column type
                DB::statement('ALTER TABLE company_people ALTER COLUMN role TYPE contact_role USING role::contact_role');
            } else {
                // For MySQL/MariaDB, keep as string but add check constraint
                $table->string('role')->nullable()->change();
            }
        });

        // For MySQL, add check constraint to ensure valid enum values
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("
                ALTER TABLE company_people
                ADD CONSTRAINT company_people_role_check
                CHECK (role IS NULL OR role IN ('primary', 'billing', 'technical', 'sales', 'support', 'other'))
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_people', function (Blueprint $table): void {
            $driver = DB::getDriverName();

            if ($driver === 'pgsql') {
                // Revert to string type
                DB::statement('ALTER TABLE company_people ALTER COLUMN role TYPE VARCHAR(255)');
            } else {
                // Remove check constraint for MySQL
                DB::statement('ALTER TABLE company_people DROP CONSTRAINT IF EXISTS company_people_role_check');
            }
        });
    }
};
