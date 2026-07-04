<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Article;
use App\Models\Team;
use App\Services\Catalog\ArticleCostResolver;
use App\Services\Erp\Financial\MarginConvention;

/**
 * Recomputes the persisted `price_review_needed` flag for an article. The
 * flag is set when a published list price yields a margin below the team
 * default against the best converted supplier cost, or when that cost can no
 * longer be converted. It never changes the public price.
 */
final readonly class RefreshArticlePriceReview
{
    public function __construct(private ArticleCostResolver $articleCostResolver) {}

    public function execute(Article $article): void
    {
        $team = $article->team;

        if (! $team instanceof Team) {
            return;
        }

        $needsReview = $this->computeNeedsReview($article, $team);

        if ($article->price_review_needed !== $needsReview) {
            $article->forceFill(['price_review_needed' => $needsReview])->saveQuietly();
        }
    }

    private function computeNeedsReview(Article $article, Team $team): bool
    {
        if ($article->list_price === null) {
            return false;
        }

        $resolution = $this->articleCostResolver->resolve($article, $team);

        if (! $resolution->hasCostData) {
            return false;
        }

        if ($resolution->convertedCost === null) {
            return true;
        }

        $marginPercent = MarginConvention::marginPercent($resolution->convertedCost, (float) $article->list_price);

        return $marginPercent < $team->getErpSettings()->default_margin_percent;
    }
}
