<?php

declare(strict_types=1);

use App\Enums\CentralPurchasingRole;
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
            $table->boolean('is_central_purchasing')->default(false)->after('is_key_account');
            $table->string('central_purchasing_role')->nullable()->after('is_central_purchasing');
        });

        // Migrate existing is_key_account=true to new fields
        DB::table('people')
            ->where('is_key_account', true)
            ->update([
                'is_central_purchasing' => true,
                'central_purchasing_role' => CentralPurchasingRole::KEY_ACCOUNT->value,
            ]);

        // For PostgreSQL, add check constraint
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE people
                ADD CONSTRAINT people_central_purchasing_role_check
                CHECK (central_purchasing_role IS NULL OR central_purchasing_role IN ('key_account', 'dept_head_sales', 'deputy_director', 'director'))
            ");
        } elseif (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("
                ALTER TABLE people
                ADD CONSTRAINT people_central_purchasing_role_check
                CHECK (central_purchasing_role IS NULL OR central_purchasing_role IN ('key_account', 'dept_head_sales', 'deputy_director', 'director'))
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn(['is_central_purchasing', 'central_purchasing_role']);
        });

        if (DB::getDriverName() === 'pgsql' || DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('ALTER TABLE people DROP CONSTRAINT IF EXISTS people_central_purchasing_role_check');
        }
    }
};
