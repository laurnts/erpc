<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->foreignId('pic_contact_id')->nullable()->after('notes')->constrained('people')->nullOnDelete();
        });

        Schema::table('people', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('name');
            $table->string('email')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropForeign(['pic_contact_id']);
        });

        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'email']);
        });
    }
};
