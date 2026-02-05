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
        Schema::create('buyer_credit_usage_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('companies')->cascadeOnDelete();
            $table->string('transaction_type', 20); // 'used' or 'restored'
            $table->decimal('amount', 15, 2);
            $table->decimal('available_credit_before', 15, 2);
            $table->decimal('available_credit_after', 15, 2);
            $table->decimal('credit_used_before', 15, 2);
            $table->decimal('credit_used_after', 15, 2);
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'buyer_id']);
            $table->index(['buyer_id', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_credit_usage_histories');
    }
};
