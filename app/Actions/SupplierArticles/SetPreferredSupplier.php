<?php

declare(strict_types=1);

namespace App\Actions\SupplierArticles;

use App\Models\SupplierArticle;
use Illuminate\Support\Facades\DB;

/**
 * Single write path for the at-most-one-preferred-supplier-per-article rule.
 * The partial unique index on supplier_articles is the schema-level backstop;
 * this action keeps the demote/promote pair atomic so callers never trip it.
 */
final readonly class SetPreferredSupplier
{
    public function execute(int $articleId, int $supplierId): void
    {
        DB::transaction(function () use ($articleId, $supplierId): void {
            $this->demoteOthers($articleId, $supplierId);

            SupplierArticle::query()
                ->where('article_id', $articleId)
                ->where('supplier_id', $supplierId)
                ->update(['is_preferred' => true]);
        });
    }

    /**
     * Demote every preferred supplier of the article except the given one.
     * Used ahead of form-driven saves that set is_preferred themselves.
     */
    public function demoteOthers(int $articleId, ?int $exceptSupplierId = null): void
    {
        SupplierArticle::query()
            ->where('article_id', $articleId)
            ->where('is_preferred', true)
            ->when($exceptSupplierId !== null, fn ($query) => $query->where('supplier_id', '!=', $exceptSupplierId))
            ->update(['is_preferred' => false]);
    }
}
