<?php

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
        Schema::table('buyer_credit_limit_request_approvals', function (Blueprint $table): void {
            // Add column as nullable first
            $table->unsignedBigInteger('team_id')->nullable()->after('id');
            $table->index('team_id');
        });

        // Populate existing records with team_id from related buyer_credit_limit_request
        \DB::statement('
            UPDATE buyer_credit_limit_request_approvals
            SET team_id = (
                SELECT team_id
                FROM buyer_credit_limit_requests
                WHERE buyer_credit_limit_requests.id = buyer_credit_limit_request_approvals.buyer_credit_limit_request_id
            )
        ');

        Schema::table('buyer_credit_limit_request_approvals', function (Blueprint $table): void {
            // Make it not nullable and add foreign key constraint
            $table->unsignedBigInteger('team_id')->nullable(false)->change();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buyer_credit_limit_request_approvals', function (Blueprint $table): void {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
        });
    }
};
