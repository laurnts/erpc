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
        Schema::table('supplier_quote_items', function (Blueprint $table): void {
            $table->boolean('is_selected')->default(false)->after('line_total');
            $table->index('is_selected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_quote_items', function (Blueprint $table): void {
            $table->dropIndex(['is_selected']);
            $table->dropColumn('is_selected');
        });
    }
};
