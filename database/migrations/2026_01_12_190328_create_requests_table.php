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
        Schema::create('requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('buyer_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_number', 50);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('stage', 50)->default('draft');
            $table->string('priority', 20)->default('normal');
            $table->date('requested_at')->nullable();
            $table->date('required_by')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'request_number']);
            $table->index(['team_id', 'stage']);
            $table->index(['team_id', 'priority']);
            $table->index(['team_id', 'buyer_id']);
            $table->index(['team_id', 'project_id']);
            $table->index(['team_id', 'is_active']);
            $table->index(['team_id', 'required_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
