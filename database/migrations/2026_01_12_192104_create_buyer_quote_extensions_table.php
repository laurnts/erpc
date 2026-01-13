<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_quote_extensions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extended_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('original_valid_until');
            $table->date('new_valid_until');
            $table->text('reason')->nullable();

            $table->timestamps();

            $table->index('buyer_quote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_quote_extensions');
    }
};
