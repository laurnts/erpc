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
        // Drop table if it exists (in case of partial migration)
        Schema::dropIfExists('buyer_credit_limit_request_approvals');

        Schema::create('buyer_credit_limit_request_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_credit_limit_request_id')
                ->constrained('buyer_credit_limit_requests')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('approved_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Prevent duplicate approvals by the same user
            $table->unique(['buyer_credit_limit_request_id', 'user_id'], 'buyer_credit_limit_request_approvals_unique');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_credit_limit_request_approvals');
    }
};
