<?php

declare(strict_types=1);

use App\Enums\SupplierQuoteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('quote_number')->unique();
            $table->string('supplier_reference')->nullable();
            $table->string('status')->default(SupplierQuoteStatus::PENDING->value);

            // Currency tracking
            $table->foreignId('currency_id')->constrained();
            $table->decimal('exchange_rate', 18, 8)->default(1);

            // Totals (in quote currency)
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);

            // Totals in base currency
            $table->decimal('subtotal_base', 18, 4)->default(0);
            $table->decimal('tax_total_base', 18, 4)->default(0);
            $table->decimal('total_base', 18, 4)->default(0);

            $table->date('quoted_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['request_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quotes');
    }
};
