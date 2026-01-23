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
        Schema::create('key_account_people_mapping', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('key_account_id')->constrained('key_accounts')->cascadeOnDelete();
            $table->foreignId('people_id')->constrained('people')->cascadeOnDelete();
            $table->unique(['key_account_id', 'people_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('key_account_people_mapping');
    }
};
