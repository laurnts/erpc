<?php

declare(strict_types=1);

namespace App\Livewire\Catalog;

use App\Models\Article;
use App\Services\Catalog\CatalogTeamResolver;
use App\Services\Catalog\QuoteCart;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Public article detail page. 404s unless the article is grid-visible for the
 * catalog team. Renders only public, whitelisted information: images, name,
 * description, tags, unit, attributes, list price, availability badge.
 *
 * Only the article id is kept as component state — the display model is
 * re-fetched through the catalog scope with whitelisted columns on every
 * render, so hydration can never resurrect confidential attributes.
 */
#[Layout('components.layouts.catalog')]
final class ArticleDetail extends Component
{
    public int $articleId;

    public string|int|float|null $quantity = 1;

    public function mount(Article $article): void
    {
        $teamId = app(CatalogTeamResolver::class)->teamId() ?? 0;

        abort_unless(
            Article::query()->inPublicCatalog($teamId)->whereKey($article->getKey())->exists(),
            404,
        );

        $this->articleId = (int) $article->getKey();
    }

    public function addToCart(): void
    {
        $teamId = app(CatalogTeamResolver::class)->teamId() ?? 0;

        $isInCatalog = Article::query()
            ->inPublicCatalog($teamId)
            ->whereKey($this->articleId)
            ->exists();

        if (! $isInCatalog) {
            $this->addError('quantity', 'This article is no longer available.');

            return;
        }

        if (! is_numeric($this->quantity) || (float) $this->quantity <= 0) {
            $this->addError('quantity', 'Quantity must be greater than zero.');

            return;
        }

        app(QuoteCart::class)->add($this->articleId, (float) $this->quantity);

        $this->quantity = 1;
        $this->resetErrorBag('quantity');
        $this->dispatch('catalog-cart-updated');
    }

    public function render(): View
    {
        $resolver = app(CatalogTeamResolver::class);
        $teamId = $resolver->teamId() ?? 0;

        $article = Article::query()
            ->inPublicCatalog($teamId)
            ->select(['articles.id', 'articles.name', 'articles.description', 'articles.unit', 'articles.attributes', 'articles.list_price'])
            ->with(['tags', 'media'])
            ->withExists([
                'suppliers as in_stock' => fn (Builder $q) => $q
                    ->where('supplier_articles.is_active', true)
                    ->where('supplier_articles.available_quantity', '>', 0),
                'suppliers as has_quantity_data' => fn (Builder $q) => $q
                    ->where('supplier_articles.is_active', true)
                    ->whereNotNull('supplier_articles.available_quantity'),
            ])
            ->findOrFail($this->articleId);

        return view('livewire.catalog.article-detail', [
            'article' => $article,
            'baseCurrency' => $resolver->team()?->getBaseCurrency(),
        ])->title($article->name.' — '.config('app.name'));
    }
}
