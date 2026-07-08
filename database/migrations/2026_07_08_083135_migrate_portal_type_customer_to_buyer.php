<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('portal_invitations')->where('portal', 'customer')->update(['portal' => 'buyer']);
        DB::table('company_portal_users')->where('portal', 'customer')->update(['portal' => 'buyer']);

        Schema::table('portal_invitations', function (Blueprint $table): void {
            $table->string('portal', 20)->default('buyer')->change();
        });

        Schema::table('company_portal_users', function (Blueprint $table): void {
            $table->string('portal', 20)->default('buyer')->change();
        });
    }

    public function down(): void
    {
        Schema::table('portal_invitations', function (Blueprint $table): void {
            $table->string('portal', 20)->default('customer')->change();
        });

        Schema::table('company_portal_users', function (Blueprint $table): void {
            $table->string('portal', 20)->default('customer')->change();
        });

        DB::table('portal_invitations')->where('portal', 'buyer')->update(['portal' => 'customer']);
        DB::table('company_portal_users')->where('portal', 'buyer')->update(['portal' => 'customer']);
    }
};
