<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table): void {
            $table->foreignId('approver_1_id')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
            $table->foreignId('approver_2_id')->nullable()->after('approver_1_id')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approver_2_id');

            // Add indexes for performance
            $table->index('approver_1_id');
            $table->index('approver_2_id');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table): void {
            $table->dropForeign(['approver_1_id']);
            $table->dropForeign(['approver_2_id']);
            $table->dropIndex(['approver_1_id']);
            $table->dropIndex(['approver_2_id']);
            $table->dropColumn(['approver_1_id', 'approver_2_id', 'approved_at']);
        });
    }
};
