<?php

declare(strict_types=1);

use App\Enums\CentralPurchasingRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Normalizes historically inconsistent `team_user` pivot rows left behind by
     * two writers that predate the shared UpdateTeamMemberRole action: the old Edit
     * Team role modal (wrote only `role`, leaving stale `central_purchasing_role`
     * and `is_approver` behind on demotions) and the 2026_01_29_043736 bulk
     * `DB::table()` migration. Idempotent and safe to re-run.
     */
    public function up(): void
    {
        DB::table('team_user')
            ->where(fn ($query) => $query->whereNull('role')->orWhere('role', '!=', 'central_purchasing'))
            ->update([
                'central_purchasing_role' => null,
                'is_approver' => false,
            ]);

        DB::table('team_user')
            ->where('role', 'central_purchasing')
            ->where(fn ($query) => $query->whereNull('central_purchasing_role')->orWhere('central_purchasing_role', '!=', CentralPurchasingRole::FINANCE->value))
            ->update([
                'is_approver' => false,
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally irreversible: cleared values were unreachable garbage.
     */
    public function down(): void
    {
        //
    }
};
