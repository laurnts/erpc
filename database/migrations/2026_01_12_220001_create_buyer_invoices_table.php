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
        Schema::create('buyer_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('invoice_number');
            $table->string('type')->default('standard'); // prepayment, balance, standard, credit_note, debit_note
            $table->string('status')->default('draft'); // draft, sent, partial, paid, overdue, cancelled

            // For credit notes - reference to original invoice
            $table->foreignId('original_invoice_id')->nullable()->constrained('buyer_invoices')->nullOnDelete();
            $table->text('credit_reason')->nullable();

            // Currency and amounts
            $table->foreignId('currency_id')->constrained();
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->decimal('amount_paid', 18, 4)->default(0);

            // Dates
            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();

            // Payment terms
            $table->integer('net_days')->default(30);

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['team_id', 'invoice_number']);
            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'request_id']);
            $table->index(['team_id', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_invoices');
    }
};
