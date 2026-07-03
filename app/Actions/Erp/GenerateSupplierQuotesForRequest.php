<?php

declare(strict_types=1);

namespace App\Actions\Erp;

use App\Models\Article;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates supplier quotes for all suppliers of articles in a request.
 *
 * For each request item that is matched to an article, this action finds
 * all suppliers for that article and creates a SupplierQuote for each
 * unique supplier, containing quote items for all articles they can supply.
 */
final readonly class GenerateSupplierQuotesForRequest
{
    /**
     * Execute the action.
     *
     * @return Collection<int, SupplierQuote> The generated supplier quotes
     */
    public function execute(Request $request): Collection
    {
        // Load request items with their articles and article suppliers
        $request->load([
            'items' => fn ($query) => $query->whereNotNull('article_id'),
            'items.article.suppliers' => fn ($query) => $query
                ->where('supplier_articles.is_active', true)
                ->where('companies.is_supplier', true),
        ]);

        // Group request items by supplier
        $itemsBySupplier = $this->groupItemsBySupplier($request->items);

        if ($itemsBySupplier->isEmpty()) {
            return collect();
        }

        // Generate quotes within a transaction
        return DB::transaction(fn (): Collection => $itemsBySupplier->map(fn (Collection $items, int $supplierId): SupplierQuote => $this->createSupplierQuote($request, $supplierId, $items))->values());
    }

    /**
     * Group request items by their suppliers.
     *
     * @param  Collection<int, RequestItem>  $items
     * @return Collection<int, Collection<int, RequestItem>> Keyed by supplier ID
     */
    private function groupItemsBySupplier(Collection $items): Collection
    {
        $itemsBySupplier = collect();

        foreach ($items as $item) {
            if ($item->article === null) {
                continue;
            }

            foreach ($item->article->suppliers as $supplier) {
                if (! $itemsBySupplier->has($supplier->getKey())) {
                    $itemsBySupplier->put($supplier->getKey(), collect());
                }
                $itemsBySupplier->get($supplier->getKey())->push($item);
            }
        }

        return $itemsBySupplier;
    }

    /**
     * Create a supplier quote with items.
     *
     * @param  Collection<int, RequestItem>  $items
     */
    private function createSupplierQuote(Request $request, int $supplierId, Collection $items): SupplierQuote
    {
        /** @var Company $supplier */
        $supplier = Company::find($supplierId);

        // Get supplier's default currency or fall back to system default
        $currencyId = $supplier->default_currency_id ?? $this->getDefaultCurrencyId();

        $quote = SupplierQuote::create([
            'team_id' => $request->team_id,
            'request_id' => $request->getKey(),
            'supplier_id' => $supplierId,
            'currency_id' => $currencyId,
            'quoted_at' => now(),
        ]);

        // Create quote items for each request item
        $sortOrder = 0;
        foreach ($items as $item) {
            $this->createQuoteItem($quote, $item, $supplierId, $sortOrder++);

            // Services main items carry their child-item breakdown into the quote
            if ($item->supportsItemHierarchy() && $item->isMainItem() && $item->children()->count() > 0) {
                $childItems = $item->children()->orderBy('sort_order')->get();
                foreach ($childItems as $childItem) {
                    $this->createChildQuoteItem($quote, $childItem, $item, $supplierId, $sortOrder++);
                }
            }
        }

        return $quote;
    }

    /**
     * Get the default currency ID from the system.
     */
    private function getDefaultCurrencyId(): ?int
    {
        return Currency::query()
            ->where('is_default', true)
            ->value('id');
    }

    /**
     * Get the last quoted price for an article from a specific supplier.
     */
    private function getLastQuotedPrice(int $articleId, int $supplierId): string
    {
        $price = DB::table('supplier_articles')
            ->where('article_id', $articleId)
            ->where('supplier_id', $supplierId)
            ->value('last_quoted_price');

        return $price !== null ? (string) $price : '0.0000';
    }

    /**
     * Create a quote item from a request item.
     */
    private function createQuoteItem(
        SupplierQuote $quote,
        RequestItem $item,
        int $supplierId,
        int $sortOrder
    ): SupplierQuoteItem {
        /** @var Article $article */
        $article = $item->article;

        // Get supplier-specific pricing from supplier_articles pivot
        $lastQuotedPrice = $this->getLastQuotedPrice($article->getKey(), $supplierId);

        $taxCode = $article->defaultTaxCode;
        $taxRate = $taxCode !== null ? (string) $taxCode->rate : '0.0000';

        return SupplierQuoteItem::create([
            'supplier_quote_id' => $quote->getKey(),
            'request_item_id' => $item->getKey(),
            'article_id' => $article->getKey(),
            'description' => $article->name,
            'quantity' => $item->quantity,
            'unit' => $item->unit ?? $article->unit,
            'unit_price' => $lastQuotedPrice,
            'tax_code_id' => $taxCode?->getKey(),
            'tax_rate' => $taxRate,
            'is_tax_inclusive' => false,
            'sort_order' => $sortOrder,
            'notes' => $item->notes,
        ]);
    }

    /**
     * Create a quote item from a child request item.
     */
    private function createChildQuoteItem(
        SupplierQuote $quote,
        RequestItem $childItem,
        RequestItem $parentItem,
        int $supplierId,
        int $sortOrder
    ): SupplierQuoteItem {
        // Child items don't have articles, so use the parent's article tax code
        $taxCode = $parentItem->article?->defaultTaxCode;
        $taxRate = $taxCode !== null ? (string) $taxCode->rate : '0.0000';

        return SupplierQuoteItem::create([
            'supplier_quote_id' => $quote->getKey(),
            'request_item_id' => $childItem->getKey(),
            'article_id' => null, // Child items don't have articles
            'description' => $childItem->description,
            'quantity' => $childItem->quantity,
            'unit' => $childItem->unit ?? 'pcs',
            'unit_price' => '0.0000', // Child items start with 0 price
            'tax_code_id' => $taxCode?->getKey(),
            'tax_rate' => $taxRate,
            'is_tax_inclusive' => $taxCode?->is_inclusive_default ?? false,
            'sort_order' => $sortOrder,
            'notes' => $childItem->notes,
        ]);
    }
}
