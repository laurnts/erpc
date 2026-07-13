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
        Schema::table('people', function (Blueprint $table): void {
            // Drop check constraints if they exist (PostgreSQL/MySQL)
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE people DROP CONSTRAINT IF EXISTS people_central_purchasing_role_check');
            } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
                DB::statement('ALTER TABLE people DROP CONSTRAINT IF EXISTS people_central_purchasing_role_check');
            }

            // Drop columns
            $table->dropColumn(['is_central_purchasing', 'central_purchasing_role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->boolean('is_central_purchasing')->default(false)->after('is_key_account');
            $table->string('central_purchasing_role')->nullable()->after('is_central_purchasing');
        });

        // Restore check constraints
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE people
                ADD CONSTRAINT people_central_purchasing_role_check
                CHECK (central_purchasing_role IS NULL OR central_purchasing_role IN ('key_account', 'dept_head_sales', 'deputy_director', 'director'))
            ");
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("
                ALTER TABLE people
                ADD CONSTRAINT people_central_purchasing_role_check
                CHECK (central_purchasing_role IS NULL OR central_purchasing_role IN ('key_account', 'dept_head_sales', 'deputy_director', 'director'))
            ");
        }
    }
};
