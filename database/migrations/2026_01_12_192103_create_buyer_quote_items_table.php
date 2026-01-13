<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_quote_item_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 50)->default('pcs');

            // Cost from supplier
            $table->decimal('cost_price', 18, 4)->default(0);

            // Selling price
            $table->decimal('unit_price', 18, 4);
            $table->decimal('unit_price_exc_tax', 18, 4);

            // Margin calculation
            $table->decimal('margin_amount', 18, 4)->default(0);
            $table->decimal('margin_percent', 8, 4)->default(0);

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

            $table->index('buyer_quote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_quote_items');
    }
};
