<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Article;
use App\Models\Currency;
use App\Models\SupplierArticle;
use App\Models\Team;
use App\Services\Currency\CurrencyService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves an article's best supplier cost in the team default currency,
 * following the canonical rungs: preferred supplier's standing price, else
 * the preferred supplier's last quoted price, else the lowest converted
 * standing price among active supplier links. A preferred-supplier cost that
 * cannot be converted aborts resolution (it never silently falls through to
 * the lowest-price rung).
 */
final readonly class ArticleCostResolver
{
    public function __construct(private CurrencyService $currencyService) {}

    public function resolve(Article $article, Team $team): ArticleCostResolution
    {
        $baseCurrency = $team->getBaseCurrency();

        if ($baseCurrency === null) {
            return new ArticleCostResolution(null, false, [
                sprintf('Team default currency "%s" is not configured as an active currency.', $team->getBaseCurrencyCode()),
            ]);
        }

        $links = SupplierArticle::query()
            ->where('article_id', $article->getKey())
            ->where('is_active', true)
            ->whereHas('supplier', fn (Builder $query): Builder => $query->where('is_supplier', true))
            ->with(['supplier', 'supplierPriceCurrency'])
            ->get();

        $preferred = $links->firstWhere('is_preferred', true);

        if ($preferred !== null && $preferred->supplier_price !== null) {
            return $this->resolvePreferredCost($preferred, (float) $preferred->supplier_price, $preferred->supplier_price_currency_id, $baseCurrency, $team, 'standing price');
        }

        if ($preferred !== null && $preferred->last_quoted_price !== null) {
            return $this->resolvePreferredCost($preferred, (float) $preferred->last_quoted_price, $preferred->last_quoted_currency_id, $baseCurrency, $team, 'last quoted price');
        }

        return $this->resolveLowestCandidateCost($links->filter(
            fn (SupplierArticle $link): bool => $link->supplier_price !== null
        )->values(), $baseCurrency, $team);
    }

    private function resolvePreferredCost(SupplierArticle $link, float $amount, ?int $currencyId, Currency $baseCurrency, Team $team, string $priceLabel): ArticleCostResolution
    {
        $fromCurrencyId = $currencyId ?? $baseCurrency->getKey();
        $converted = $this->currencyService->convert($amount, $fromCurrencyId, $baseCurrency->getKey(), null, $team);

        if ($converted === null) {
            return new ArticleCostResolution(null, true, [
                sprintf(
                    'Missing exchange rate from %s to %s for the preferred supplier\'s %s.',
                    $this->currencyCode($fromCurrencyId),
                    $baseCurrency->code,
                    $priceLabel,
                ),
            ]);
        }

        return new ArticleCostResolution($converted, true, []);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SupplierArticle>  $candidates
     */
    private function resolveLowestCandidateCost(\Illuminate\Support\Collection $candidates, Currency $baseCurrency, Team $team): ArticleCostResolution
    {
        if ($candidates->isEmpty()) {
            return new ArticleCostResolution(null, false, ['No supplier cost is available for this article.']);
        }

        $converted = [];
        $skipped = [];

        foreach ($candidates as $link) {
            $fromCurrencyId = $link->supplier_price_currency_id ?? $baseCurrency->getKey();
            $value = $this->currencyService->convert((float) $link->supplier_price, $fromCurrencyId, $baseCurrency->getKey(), null, $team);

            if ($value === null) {
                $skipped[] = sprintf('%s (%s)', $link->supplier->name, $this->currencyCode($fromCurrencyId));

                continue;
            }

            $converted[] = $value;
        }

        if ($converted === []) {
            return new ArticleCostResolution(null, true, [
                sprintf('No supplier price could be converted to %s — missing exchange rates for: %s.', $baseCurrency->code, implode(', ', $skipped)),
            ]);
        }

        $notices = [];

        if ($skipped !== []) {
            $notices[] = sprintf('Skipped offers without an exchange rate to %s: %s.', $baseCurrency->code, implode(', ', $skipped));
        }

        return new ArticleCostResolution(min($converted), true, $notices);
    }

    private function currencyCode(int $currencyId): string
    {
        $code = Currency::query()->whereKey($currencyId)->value('code');

        return is_string($code) ? $code : sprintf('currency #%d', $currencyId);
    }
}
