<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One counter row per (team, document key, period). Allocation takes the row
 * under SELECT ... FOR UPDATE, so concurrent creates serialise on the counter
 * instead of racing a read-max query.
 *
 * Counter-row numbering: sequences gap on rollback, so a locked counter row is
 * used instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('period');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['team_id', 'key', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_sequences');
    }
};
