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
        Schema::table('profit_and_losses', function (Blueprint $table): void {
            $table->foreignId('dept_head_sales_id')
                ->nullable()
                ->after('prepared_by_id')
                ->constrained('people')
                ->nullOnDelete();
            
            $table->foreignId('deputy_director_id')
                ->nullable()
                ->after('dept_head_sales_id')
                ->constrained('people')
                ->nullOnDelete();
            
            $table->foreignId('approved_by_id')
                ->nullable()
                ->after('deputy_director_id')
                ->constrained('people')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profit_and_losses', function (Blueprint $table): void {
            $table->dropForeign(['dept_head_sales_id']);
            $table->dropForeign(['deputy_director_id']);
            $table->dropForeign(['approved_by_id']);
            
            $table->dropColumn(['dept_head_sales_id', 'deputy_director_id', 'approved_by_id']);
        });
    }
};
