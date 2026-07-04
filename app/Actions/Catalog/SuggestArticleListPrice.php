<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Article;
use App\Models\Team;
use App\Services\Catalog\ArticleCostResolver;
use App\Services\Erp\Financial\MarginConvention;

/**
 * Computes a list-price suggestion from the article's best supplier cost and
 * the team's default margin. The suggestion is advisory only — it never
 * writes to the article; saving the form is the publish act.
 */
final readonly class SuggestArticleListPrice
{
    public function __construct(private ArticleCostResolver $articleCostResolver) {}

    /**
     * @return array{price: float|null, notices: list<string>}
     */
    public function execute(Article $article, Team $team): array
    {
        $resolution = $this->articleCostResolver->resolve($article, $team);

        if ($resolution->convertedCost === null) {
            return ['price' => null, 'notices' => $resolution->notices];
        }

        $marginPercent = $team->getErpSettings()->default_margin_percent;
        $price = round(MarginConvention::netUnitPrice($resolution->convertedCost, $marginPercent), 4);

        return ['price' => $price, 'notices' => $resolution->notices];
    }
}
