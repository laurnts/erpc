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
        Schema::create('request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 15, 4)->default(1);
            $table->string('unit', 20)->default('pcs');
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_matched')->default(false);
            $table->timestamps();

            $table->index(['request_id', 'sort_order']);
            $table->index(['request_id', 'is_matched']);
            $table->index(['article_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};
