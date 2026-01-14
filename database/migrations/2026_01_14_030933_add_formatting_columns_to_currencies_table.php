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
        Schema::table('currencies', function (Blueprint $table): void {
            $table->string('thousands_separator', 1)->default(',')->after('decimal_places');
            $table->string('decimal_separator', 1)->default('.')->after('thousands_separator');
            $table->string('symbol_position', 10)->default('before')->after('decimal_separator');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table): void {
            $table->dropColumn(['thousands_separator', 'decimal_separator', 'symbol_position']);
        });
    }
};
