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
        Schema::create('buyer_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('buyer_invoice_id')->constrained()->cascadeOnDelete();

            $table->string('payment_number');
            $table->string('payment_method'); // bank_transfer, cash, check, lc, other
            $table->decimal('amount', 18, 4);

            $table->date('payment_date');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['team_id', 'payment_number']);
            $table->index(['team_id', 'buyer_invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_payments');
    }
};
