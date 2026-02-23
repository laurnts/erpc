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
        Schema::create('payment_document_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedBigInteger('media_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('approved_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign key for media_id (media table from Spatie MediaLibrary)
            $table->foreign('media_id')
                ->references('id')
                ->on('media')
                ->cascadeOnDelete();

            // Prevent duplicate approvals by the same user for the same document
            $table->unique(['media_id', 'user_id'], 'payment_document_approvals_unique');
            $table->index('user_id');
            $table->index('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_document_approvals');
    }
};
