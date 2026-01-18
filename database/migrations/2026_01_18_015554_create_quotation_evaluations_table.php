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
        Schema::create('quotation_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->string('qe_number', 50);
            $table->text('description')->nullable();
            $table->date('qe_date');

            // Central Purchasing - approval workflow
            $table->foreignId('prepared_by_id')->nullable()->constrained('key_accounts')->nullOnDelete();
            $table->string('dept_head_sales_name')->nullable();
            $table->string('deputy_director_name')->nullable();
            $table->string('approved_by_name')->nullable();

            // Snapshot data (items, suppliers, totals)
            $table->json('data');

            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'qe_number']);
            $table->index('request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_evaluations');
    }
};
