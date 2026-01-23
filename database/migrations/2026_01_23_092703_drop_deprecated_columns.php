<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to drop deprecated columns.
 *
 * This migration is DISABLED by default. Enable it in a future release after ensuring
 * all code has been migrated away from deprecated columns.
 *
 * To enable: Remove the `return;` statement at the start of the `up()` method.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // DISABLED: Uncomment after migration period (e.g., 2 releases)
        // return;

        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            $table->dropColumn([
                'dept_head_sales_name',
                'deputy_director_name',
                'approved_by_name',
            ]);
        });

        Schema::table('profit_and_losses', function (Blueprint $table): void {
            $table->dropColumn([
                'dept_head_sales_name',
                'deputy_director_name',
                'approved_by_name',
            ]);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('contact_person');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            $table->string('dept_head_sales_name')->nullable();
            $table->string('deputy_director_name')->nullable();
            $table->string('approved_by_name')->nullable();
        });

        Schema::table('profit_and_losses', function (Blueprint $table): void {
            $table->string('dept_head_sales_name')->nullable();
            $table->string('deputy_director_name')->nullable();
            $table->string('approved_by_name')->nullable();
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->string('contact_person')->nullable();
        });
    }
};
