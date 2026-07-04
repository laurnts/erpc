<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->decimal('list_price', 15, 4)->nullable()->after('is_active');
            $table->dateTime('list_price_updated_at')->nullable()->after('list_price');
            $table->boolean('show_in_product_grid')->default(false)->after('list_price_updated_at');
            $table->boolean('price_review_needed')->default(false)->after('show_in_product_grid');

            $table->index(['team_id', 'show_in_product_grid']);
            $table->index('price_review_needed');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'show_in_product_grid']);
            $table->dropIndex(['price_review_needed']);
            $table->dropColumn([
                'list_price',
                'list_price_updated_at',
                'show_in_product_grid',
                'price_review_needed',
            ]);
        });
    }
};
