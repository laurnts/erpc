<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Portal memberships now exist from the moment of invitation: user_id
     * becomes nullable and invited_name/invited_email carry the person's
     * identity until acceptance links a user. Backfills an Invited-state row
     * (inactive, no user — grants no access by construction) for every
     * pending invitation.
     */
    public function up(): void
    {
        Schema::table('company_portal_users', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('invited_name')->nullable()->after('user_id');
            $table->string('invited_email')->nullable()->after('invited_name');
        });

        DB::table('portal_invitations')
            ->whereNull('accepted_at')
            ->orderBy('id')
            ->chunkById(200, function ($invitations): void {
                foreach ($invitations as $invitation) {
                    $exists = DB::table('company_portal_users')
                        ->where('company_id', $invitation->company_id)
                        ->where('portal', $invitation->portal)
                        ->whereNull('user_id')
                        ->where('invited_email', $invitation->email)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('company_portal_users')->insert([
                        'team_id' => $invitation->team_id,
                        'company_id' => $invitation->company_id,
                        'user_id' => null,
                        'portal' => $invitation->portal,
                        'invited_by' => $invitation->invited_by,
                        'is_active' => false,
                        'invited_name' => $invitation->name,
                        'invited_email' => $invitation->email,
                        'created_at' => $invitation->created_at,
                        'updated_at' => $invitation->created_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('company_portal_users')->whereNull('user_id')->delete();

        Schema::table('company_portal_users', function (Blueprint $table): void {
            $table->dropColumn(['invited_name', 'invited_email']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
