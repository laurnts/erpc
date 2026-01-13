<?php

declare(strict_types=1);

use App\Enums\BuyerQuoteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('quote_number')->unique();
            $table->integer('version')->default(1);
            $table->foreignId('previous_version_id')->nullable()->constrained('buyer_quotes')->nullOnDelete();

            $table->string('status')->default(BuyerQuoteStatus::DRAFT->value);

            // Currency tracking
            $table->foreignId('currency_id')->constrained();
            $table->decimal('exchange_rate', 18, 8)->default(1);

            // Totals
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);

            // Payment terms
            $table->integer('prepayment_percent')->default(0);
            $table->integer('payment_terms_days')->default(30);
            $table->text('payment_terms_description')->nullable();

            $table->date('issued_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['request_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_quotes');
    }
};
