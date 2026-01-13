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
        Schema::create('supplier_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('companies')->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->decimal('last_quoted_price', 15, 2)->nullable();
            $table->foreignId('last_quoted_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->dateTime('last_quoted_at')->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['article_id', 'supplier_id']);
            $table->index(['article_id', 'is_preferred']);
            $table->index(['supplier_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_articles');
    }
};
