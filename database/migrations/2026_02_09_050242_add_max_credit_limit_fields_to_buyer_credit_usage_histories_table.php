<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('buyer_credit_usage_histories', function (Blueprint $table): void {
            $table->decimal('max_credit_limit_before', 15, 2)->default(0)->after('credit_used_after');
            $table->decimal('max_credit_limit_after', 15, 2)->default(0)->after('max_credit_limit_before');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buyer_credit_usage_histories', function (Blueprint $table): void {
            $table->dropColumn(['max_credit_limit_before', 'max_credit_limit_after']);
        });
    }
};
