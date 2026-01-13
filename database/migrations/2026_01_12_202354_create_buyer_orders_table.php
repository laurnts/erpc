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
        Schema::create('buyer_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('buyer_quote_id')->nullable()->constrained()->nullOnDelete();

            $table->string('order_number')->unique();
            $table->string('status')->default(OrderStatus::DRAFT->value);

            // Locked totals (copied from quote, not editable)
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            // Locked payment terms (copied from quote)
            $table->integer('payment_terms_days')->default(30);
            $table->text('payment_terms_text')->nullable();

            // Notes
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Timestamps
            $table->dateTime('ordered_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['request_id']);
            $table->index(['buyer_id']);
            $table->index(['buyer_quote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_orders');
    }
};
