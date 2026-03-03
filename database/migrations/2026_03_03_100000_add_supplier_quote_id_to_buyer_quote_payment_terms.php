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
        Schema::table('buyer_quote_payment_terms', function (Blueprint $table): void {
            $table->foreignId('supplier_quote_id')
                ->nullable()
                ->after('buyer_quote_id')
                ->constrained('supplier_quotes')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buyer_quote_payment_terms', function (Blueprint $table): void {
            $table->dropForeign(['supplier_quote_id']);
        });
    }
};
