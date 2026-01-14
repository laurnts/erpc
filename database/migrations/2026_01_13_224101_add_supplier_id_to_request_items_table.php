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
        Schema::table('request_items', function (Blueprint $table): void {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('article_id')
                ->constrained('companies')
                ->nullOnDelete();

            $table->index(['request_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_items', function (Blueprint $table): void {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['request_id', 'supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
