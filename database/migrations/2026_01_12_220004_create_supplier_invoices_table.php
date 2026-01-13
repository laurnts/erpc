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
        Schema::create('supplier_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_order_id')->nullable()->constrained()->nullOnDelete();

            // Supplier's invoice number (external) - not unique because different suppliers may use same numbers
            $table->string('invoice_number');
            // Our internal reference number (auto-generated, unique)
            $table->string('reference_number')->unique();

            $table->string('type')->default('standard'); // prepayment, balance, standard, credit_note, debit_note
            $table->string('status')->default('draft'); // draft, sent, partial, paid, overdue, cancelled

            // Credit note support
            $table->foreignId('original_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
            $table->text('credit_reason')->nullable();

            // Multi-currency support with exchange rate snapshot
            $table->foreignId('currency_id')->constrained();
            $table->decimal('exchange_rate', 18, 8)->default(1);

            // Amounts
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->decimal('amount_paid', 18, 4)->default(0);

            // Payment terms
            $table->date('invoice_date')->nullable();
            $table->date('due_at')->nullable();
            $table->integer('net_days')->default(30);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['team_id', 'supplier_id']);
            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
