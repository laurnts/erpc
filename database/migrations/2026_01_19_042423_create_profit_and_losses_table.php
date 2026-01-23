<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_and_losses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_quote_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pnl_number')->unique();
            $table->string('description')->nullable();
            $table->date('pnl_date');
            $table->foreignId('prepared_by_id')->nullable()->constrained('key_accounts')->nullOnDelete();
            $table->string('dept_head_sales_name')->nullable();
            $table->string('deputy_director_name')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_and_losses');
    }
};
