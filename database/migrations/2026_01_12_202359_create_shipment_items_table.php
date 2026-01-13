<?php

declare(strict_types=1);

use App\Enums\ItemCondition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();

            // Link to order items (one or the other based on shipment type)
            $table->foreignId('supplier_order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_order_item_id')->nullable()->constrained()->cascadeOnDelete();

            // Quantity tracking
            $table->decimal('quantity_shipped', 15, 4);
            $table->decimal('quantity_received', 15, 4)->nullable(); // Filled on delivery

            // Condition tracking
            $table->string('condition')->default(ItemCondition::GOOD->value);
            $table->text('condition_notes')->nullable();

            // Ordering
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
