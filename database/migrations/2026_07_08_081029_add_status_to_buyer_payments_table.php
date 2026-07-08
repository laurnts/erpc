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
        Schema::table('buyer_payments', function (Blueprint $table): void {
            $table->string('status')->default('confirmed')->after('amount');
            $table->string('submitted_actor_type')->nullable()->after('status');
            $table->foreignId('submitted_by_id')->nullable()->after('submitted_actor_type')->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by_id')->nullable()->after('submitted_by_id')->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by_id');

            $table->index(['team_id', 'status']);
        });

        DB::table('buyer_payments')->update(['status' => 'confirmed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buyer_payments', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'status']);
            $table->dropConstrainedForeignId('submitted_by_id');
            $table->dropConstrainedForeignId('confirmed_by_id');
            $table->dropColumn(['status', 'submitted_actor_type', 'confirmed_at']);
        });
    }
};
