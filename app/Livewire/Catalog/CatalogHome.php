<?php

declare(strict_types=1);

namespace App\Livewire\Catalog;

use App\Models\Article;
use App\Models\Tag;
use App\Services\Catalog\CatalogTeamResolver;
use App\Services\Catalog\QuoteCart;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Public catalog homepage: category menu from tags, debounced search over
 * name/SKU/description, paginated article grid with add-to-quote controls.
 * Everything rendered here is whitelist-only — supplier identities, costs,
 * margins, and article codes are never selected (architecture §F).
 */
#[Layout('components.layouts.catalog')]
final class CatalogHome extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?int $category = null;

    /**
     * @var array<int, string|int|float|null>
     */
    public array $quantities = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function selectCategory(?int $tagId): void
    {
        $this->category = $tagId;
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function addToCart(int $articleId): void
    {
        $teamId = app(CatalogTeamResolver::class)->teamId() ?? 0;

        $isInCatalog = Article::query()
            ->inPublicCatalog($teamId)
            ->whereKey($articleId)
            ->exists();

        if (! $isInCatalog) {
            $this->addError('quantities.'.$articleId, 'This article is no longer available.');

            return;
        }

        $quantity = $this->quantities[$articleId] ?? 1;

        if (! is_numeric($quantity) || (float) $quantity <= 0) {
            $this->addError('quantities.'.$articleId, 'Quantity must be greater than zero.');

            return;
        }

        app(QuoteCart::class)->add($articleId, (float) $quantity);

        unset($this->quantities[$articleId]);
        $this->resetErrorBag('quantities.'.$articleId);
        $this->dispatch('catalog-cart-updated');
    }

    public function render(): View
    {
        $resolver = app(CatalogTeamResolver::class);
        $teamId = $resolver->teamId() ?? 0;

        $query = Article::query()
            ->inPublicCatalog($teamId)
            ->select(['articles.id', 'articles.name', 'articles.description', 'articles.unit', 'articles.list_price'])
            ->with(['tags', 'media'])
            ->withExists([
                'suppliers as in_stock' => fn (Builder $q) => $q
                    ->where('supplier_articles.is_active', true)
                    ->where('supplier_articles.available_quantity', '>', 0),
                'suppliers as has_quantity_data' => fn (Builder $q) => $q
                    ->where('supplier_articles.is_active', true)
                    ->whereNotNull('supplier_articles.available_quantity'),
            ]);

        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.mb_strtolower($term).'%';

            $query->where(function (Builder $q) use ($like): void {
                $q->whereRaw('LOWER(articles.name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(articles.sku) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(articles.description) LIKE ?', [$like]);
            });
        }

        if ($this->category !== null) {
            $query->whereHas('tags', fn (Builder $q) => $q->where('tags.id', $this->category));
        }

        $articles = $query->orderBy('articles.name')->paginate(12);

        $categories = Tag::query()
            ->where('tags.team_id', $teamId)
            ->where('tags.is_active', true)
            ->whereHas('articles', fn (Builder $q): Builder => $q
                ->where('articles.team_id', $teamId)
                ->where('articles.is_active', true)
                ->where('articles.show_in_product_grid', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['tags.id', 'tags.name', 'tags.color']);

        return view('livewire.catalog.catalog-home', [
            'articles' => $articles,
            'categories' => $categories,
            'baseCurrency' => $resolver->team()?->getBaseCurrency(),
        ])->title(config('app.name').' — Article Catalog');
    }
}
