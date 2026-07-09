<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The 2026_07_04 migration that introduced the portal column widened the
     * unique index on company_portal_users but left portal_invitations at
     * (company_id, email), which blocks inviting the same email to the second
     * portal of a dual-role company.
     */
    public function up(): void
    {
        Schema::table('portal_invitations', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'email']);
            $table->unique(['company_id', 'email', 'portal']);
        });
    }

    public function down(): void
    {
        // Restoring the narrow unique index fails if any email holds
        // invitations for both portals at one company; remove one of the
        // duplicate invitations manually before rolling back.
        Schema::table('portal_invitations', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'email', 'portal']);
            $table->unique(['company_id', 'email']);
        });
    }
};
