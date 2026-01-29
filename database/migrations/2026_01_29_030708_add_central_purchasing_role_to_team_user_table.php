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
        Schema::table('team_user', function (Blueprint $table) {
            $table->string('central_purchasing_role')->nullable()->after('role');
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('central_purchasing_role')->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_user', function (Blueprint $table) {
            $table->dropColumn('central_purchasing_role');
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropColumn('central_purchasing_role');
        });
    }
};
