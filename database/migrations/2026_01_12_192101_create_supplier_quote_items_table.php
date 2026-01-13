<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 50)->default('pcs');

            // Pricing
            $table->decimal('unit_price', 18, 4);
            $table->decimal('unit_price_exc_tax', 18, 4);

            // Tax handling (item-level)
            $table->foreignId('tax_code_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_tax_inclusive')->default(false);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);

            // Line totals
            $table->decimal('line_subtotal', 18, 4)->default(0);
            $table->decimal('line_tax', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4)->default(0);

            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('supplier_quote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_items');
    }
};
