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
        Schema::table('companies', function (Blueprint $table): void {
            // Active credit limit - static, only changes when approved
            $table->decimal('active_credit_limit', 15, 2)->default(0)->after('credit_limit');
            // Requested credit limit - for pending requests
            $table->decimal('requested_credit_limit', 15, 2)->nullable()->after('active_credit_limit');
        });

        // Migrate existing credit_limit values to active_credit_limit
        DB::table('companies')
            ->where('is_buyer', true)
            ->update([
                'active_credit_limit' => DB::raw('credit_limit'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['active_credit_limit', 'requested_credit_limit']);
        });
    }
};
