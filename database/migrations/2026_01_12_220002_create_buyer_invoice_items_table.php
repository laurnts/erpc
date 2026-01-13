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
        Schema::create('buyer_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();

            $table->text('description');
            $table->decimal('quantity', 18, 4);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 18, 4);

            // Tax fields
            $table->foreignId('tax_code_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->boolean('tax_inclusive')->default(false);

            // Calculated line totals
            $table->decimal('line_subtotal', 18, 4)->default(0);
            $table->decimal('line_tax', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4)->default(0);

            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index('buyer_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_invoice_items');
    }
};
