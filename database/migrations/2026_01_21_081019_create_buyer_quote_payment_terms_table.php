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
        Schema::create('buyer_quote_payment_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_quote_id')->constrained('buyer_quotes')->cascadeOnDelete();
            $table->integer('due_days')->default(0);
            $table->integer('percentage')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['buyer_quote_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_quote_payment_terms');
    }
};
