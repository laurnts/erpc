<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_quote_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 50)->default('pcs');

            // Pricing (locked from quote)
            $table->decimal('unit_price', 18, 4);
            $table->decimal('unit_price_exc_tax', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4)->default(0);

            // Tax handling - locked from quote at order time
            $table->foreignId('tax_code_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_tax_inclusive')->default(false);
            $table->decimal('tax_rate', 8, 4)->default(0);

            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('supplier_order_id');
            $table->index('supplier_quote_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_items');
    }
};
