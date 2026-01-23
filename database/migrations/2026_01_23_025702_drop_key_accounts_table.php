<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop mapping table first (no longer needed)
        Schema::dropIfExists('key_account_people_mapping');

        // Drop key_accounts table
        Schema::dropIfExists('key_accounts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate key_accounts table (structure from original migration)
        Schema::create('key_accounts', function ($table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('team_id');
            $table->index('is_active');
        });

        // Recreate mapping table
        Schema::create('key_account_people_mapping', function ($table): void {
            $table->id();
            $table->foreignId('key_account_id')->constrained('key_accounts')->cascadeOnDelete();
            $table->foreignId('people_id')->constrained('people')->cascadeOnDelete();
            $table->unique(['key_account_id', 'people_id']);
        });
    }
};
