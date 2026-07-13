<?php

declare(strict_types=1);

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
        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            $table->string('status', 20)->default('need_approval')->after('approved_by_id');
            $table->timestamp('dept_head_sales_approved_at')->nullable()->after('status');
            $table->timestamp('deputy_director_approved_at')->nullable()->after('dept_head_sales_approved_at');
            $table->timestamp('director_approved_at')->nullable()->after('deputy_director_approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            $table->dropColumn([
                'status',
                'dept_head_sales_approved_at',
                'deputy_director_approved_at',
                'director_approved_at',
            ]);
        });
    }
};
