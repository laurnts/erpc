<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_quote_id')->nullable()->constrained()->nullOnDelete();

            // Currency and exchange rate snapshot
            $table->foreignId('currency_id')->constrained();
            $table->decimal('exchange_rate', 18, 8)->default(1);

            // PO number with suffix for multiple orders per request (PO-YYYY-NNNN-A/B/C)
            $table->string('po_number')->unique();
            $table->string('status')->default(OrderStatus::DRAFT->value);

            // Totals in order currency
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);

            // Totals in base currency
            $table->decimal('base_subtotal', 18, 4)->default(0);
            $table->decimal('base_tax_total', 18, 4)->default(0);
            $table->decimal('base_total', 18, 4)->default(0);

            // Payment terms
            $table->integer('payment_terms_days')->nullable();
            $table->string('payment_terms_text')->nullable();

            // Delivery
            $table->date('expected_delivery_date')->nullable();

            // Notes
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Status timestamps
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['team_id', 'status']);
            $table->index(['request_id', 'supplier_id']);
            $table->index(['supplier_quote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_orders');
    }
};
