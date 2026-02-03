<?php

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
        Schema::create('acceptance_report_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('acceptance_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_item_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['acceptance_report_id', 'request_item_id']);
            $table->index(['acceptance_report_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acceptance_report_items');
    }
};
