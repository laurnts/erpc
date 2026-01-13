<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_quote_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 50)->default('pcs');

            // Pricing (locked from quote)
            $table->decimal('unit_price', 15, 2);
            $table->decimal('unit_price_exc_tax', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);

            // Locked tax info (copied from quote item)
            $table->foreignId('tax_code_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_tax_inclusive')->default(false);
            $table->decimal('tax_rate', 8, 4)->default(0);

            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('buyer_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_order_items');
    }
};
