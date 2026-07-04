<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_invitations', function (Blueprint $table): void {
            $table->string('portal', 20)->default('customer')->after('name');
        });

        Schema::table('company_portal_users', function (Blueprint $table): void {
            $table->string('portal', 20)->default('customer')->after('user_id');
        });

        Schema::table('company_portal_users', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'user_id']);
            $table->unique(['company_id', 'user_id', 'portal']);
        });
    }

    public function down(): void
    {
        // Restoring the narrow unique index fails if any user holds both a
        // customer and a supplier membership at one company; remove one of the
        // duplicate memberships manually before rolling back.
        Schema::table('company_portal_users', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'user_id', 'portal']);
            $table->unique(['company_id', 'user_id']);
        });

        Schema::table('company_portal_users', function (Blueprint $table): void {
            $table->dropColumn('portal');
        });

        Schema::table('portal_invitations', function (Blueprint $table): void {
            $table->dropColumn('portal');
        });
    }
};
