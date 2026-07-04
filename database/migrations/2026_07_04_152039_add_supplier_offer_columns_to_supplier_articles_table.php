<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_articles', function (Blueprint $table): void {
            $table->decimal('supplier_price', 15, 4)->nullable()->after('supplier_sku');
            $table->foreignId('supplier_price_currency_id')->nullable()->after('supplier_price')->constrained('currencies')->nullOnDelete();
            $table->dateTime('supplier_price_updated_at')->nullable()->after('supplier_price_currency_id');
            $table->decimal('available_quantity', 15, 4)->nullable()->after('supplier_price_updated_at');
            $table->dateTime('quantity_updated_at')->nullable()->after('available_quantity');

            $table->index(['article_id', 'is_active']);
        });

        // Deterministically resolve articles that accumulated more than one
        // preferred supplier before enforcing at-most-one at the schema level.
        DB::statement(<<<'SQL'
            UPDATE supplier_articles
            SET is_preferred = false
            WHERE is_preferred = true
              AND id NOT IN (
                  SELECT MIN(id) FROM supplier_articles
                  WHERE is_preferred = true
                  GROUP BY article_id
              )
        SQL);

        DB::statement('CREATE UNIQUE INDEX supplier_articles_one_preferred_per_article ON supplier_articles (article_id) WHERE is_preferred');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS supplier_articles_one_preferred_per_article');

        Schema::table('supplier_articles', function (Blueprint $table): void {
            $table->dropIndex(['article_id', 'is_active']);
            $table->dropConstrainedForeignId('supplier_price_currency_id');
            $table->dropColumn([
                'supplier_price',
                'supplier_price_updated_at',
                'available_quantity',
                'quantity_updated_at',
            ]);
        });
    }
};
