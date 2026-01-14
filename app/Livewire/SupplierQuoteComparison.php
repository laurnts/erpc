<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SupplierQuoteStatus;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Component for comparing supplier quotes side by side.
 * Shows prices per item across all suppliers with best price highlighting.
 *
 * @property-read Collection<int, SupplierQuote> $quotes
 * @property-read Collection<int, RequestItem> $requestItems
 * @property-read array<int, array<int, SupplierQuoteItem|null>> $priceMatrix
 * @property-read array<int, int|null> $bestPricesByItem
 * @property-read int|null $bestOverallQuoteId
 * @property-read float $selectionTotal
 * @property-read int $selectedSuppliersCount
 * @property-read bool $hasQuotes
 */
final class SupplierQuoteComparison extends BaseLivewireComponent
{
    public Request $request;

    /**
     * Track which supplier is selected for each request item.
     * Key: request_item_id, Value: supplier_quote_id
     *
     * @var array<int, int|null>
     */
    public array $itemSelections = [];

    public function mount(Request $request): void
    {
        $this->request = $request;
        $this->initializeSelections();
    }

    /**
     * Initialize selections from already selected quotes.
     */
    private function initializeSelections(): void
    {
        // Get currently selected quote items and map them
        $selectedQuotes = $this->request->supplierQuotes()
            ->where('status', SupplierQuoteStatus::SELECTED)
            ->with('items')
            ->get();

        foreach ($selectedQuotes as $quote) {
            foreach ($quote->items as $item) {
                if ($item->request_item_id !== null) {
                    $this->itemSelections[$item->request_item_id] = $quote->getKey();
                }
            }
        }
    }

    /**
     * Select a supplier for a specific item.
     */
    public function selectSupplierForItem(int $requestItemId, int $supplierQuoteId): void
    {
        // Toggle off if already selected
        if (($this->itemSelections[$requestItemId] ?? null) === $supplierQuoteId) {
            unset($this->itemSelections[$requestItemId]);
        } else {
            $this->itemSelections[$requestItemId] = $supplierQuoteId;
        }
    }

    /**
     * Select all items from a single supplier.
     */
    public function selectSingleSupplier(int $supplierQuoteId): void
    {
        $quote = $this->quotes->firstWhere('id', $supplierQuoteId);
        if ($quote === null) {
            return;
        }

        foreach ($this->requestItems as $requestItem) {
            $quoteItem = $this->priceMatrix[$requestItem->getKey()][$supplierQuoteId] ?? null;
            if ($quoteItem !== null) {
                $this->itemSelections[$requestItem->getKey()] = $supplierQuoteId;
            }
        }

        Notification::make()
            ->title('Supplier selected')
            ->body("All available items selected from {$quote->supplier->name}.")
            ->success()
            ->send();
    }

    /**
     * Auto-select based on best prices.
     */
    public function selectBestPrices(): void
    {
        foreach ($this->bestPricesByItem as $requestItemId => $bestQuoteId) {
            if ($bestQuoteId !== null) {
                $this->itemSelections[$requestItemId] = $bestQuoteId;
            }
        }

        Notification::make()
            ->title('Best prices selected')
            ->body('All items have been assigned to suppliers with the lowest prices.')
            ->success()
            ->send();
    }

    /**
     * Clear all selections.
     */
    public function clearSelections(): void
    {
        $this->itemSelections = [];

        Notification::make()
            ->title('Selections cleared')
            ->info()
            ->send();
    }

    /**
     * Apply the current selections by updating quote statuses.
     */
    public function applySelections(): void
    {
        // Group selections by quote
        $quoteSelections = [];
        foreach ($this->itemSelections as $requestItemId => $quoteId) {
            if ($quoteId !== null) {
                $quoteSelections[$quoteId][] = $requestItemId;
            }
        }

        // Mark selected quotes
        foreach ($this->quotes as $quote) {
            if (array_key_exists($quote->getKey(), $quoteSelections)) {
                $quote->markAsSelected();
            } elseif ($quote->status === SupplierQuoteStatus::SELECTED) {
                // Quote was selected but no longer has selections, mark as pending
                $quote->status = SupplierQuoteStatus::PENDING;
                $quote->save();
            }
        }

        Notification::make()
            ->title('Selections applied')
            ->body('Quote statuses have been updated.')
            ->success()
            ->send();

        $this->dispatch('selections-applied');
    }

    /**
     * Get all active supplier quotes for this request.
     *
     * @return Collection<int, SupplierQuote>
     */
    #[Computed]
    public function quotes(): Collection
    {
        return $this->request->supplierQuotes()
            ->whereIn('status', [SupplierQuoteStatus::PENDING, SupplierQuoteStatus::SELECTED])
            ->with(['supplier', 'currency', 'items.requestItem'])
            ->orderBy('total_base')
            ->get();
    }

    /**
     * Get all request items.
     *
     * @return Collection<int, RequestItem>
     */
    #[Computed]
    public function requestItems(): Collection
    {
        return $this->request->items()
            ->with('article')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Build a matrix of prices: [request_item_id][supplier_quote_id] => SupplierQuoteItem|null
     *
     * @return array<int, array<int, SupplierQuoteItem|null>>
     */
    #[Computed]
    public function priceMatrix(): array
    {
        $matrix = [];

        foreach ($this->requestItems as $requestItem) {
            $matrix[$requestItem->getKey()] = [];

            foreach ($this->quotes as $quote) {
                $quoteItem = $quote->items->first(
                    fn (SupplierQuoteItem $item): bool => $item->request_item_id === $requestItem->getKey()
                );
                $matrix[$requestItem->getKey()][$quote->getKey()] = $quoteItem;
            }
        }

        return $matrix;
    }

    /**
     * Find the best (lowest) price for each request item.
     *
     * @return array<int, int|null>
     */
    #[Computed]
    public function bestPricesByItem(): array
    {
        $bestPrices = [];

        foreach ($this->priceMatrix as $requestItemId => $quoteItems) {
            $bestQuoteId = null;
            $bestUnitPriceBase = null;

            foreach ($quoteItems as $quoteId => $quoteItem) {
                if ($quoteItem === null) {
                    continue;
                }

                $quote = $this->quotes->firstWhere('id', $quoteId);
                if ($quote === null) {
                    continue;
                }

                // Compare unit price in base currency
                $unitPriceBase = (float) $quoteItem->unit_price_exc_tax * (float) $quote->exchange_rate;

                if ($bestUnitPriceBase === null || $unitPriceBase < $bestUnitPriceBase) {
                    $bestUnitPriceBase = $unitPriceBase;
                    $bestQuoteId = $quoteId;
                }
            }

            $bestPrices[$requestItemId] = $bestQuoteId;
        }

        return $bestPrices;
    }

    /**
     * Get the quote with the lowest total (base currency).
     */
    #[Computed]
    public function bestOverallQuoteId(): ?int
    {
        $quotes = $this->quotes;
        if ($quotes->isEmpty()) {
            return null;
        }

        return $quotes->sortBy('total_base')->first()?->getKey();
    }

    /**
     * Calculate total cost based on current selections.
     */
    #[Computed]
    public function selectionTotal(): float
    {
        $total = 0.0;

        foreach ($this->itemSelections as $requestItemId => $quoteId) {
            if ($quoteId === null) {
                continue;
            }

            $quoteItem = $this->priceMatrix[$requestItemId][$quoteId] ?? null;
            if ($quoteItem === null) {
                continue;
            }

            $quote = $this->quotes->firstWhere('id', $quoteId);
            if ($quote === null) {
                continue;
            }

            // Line total in base currency
            $lineTotalBase = (float) $quoteItem->line_total * (float) $quote->exchange_rate;
            $total += $lineTotalBase;
        }

        return $total;
    }

    /**
     * Get the number of suppliers in current selections.
     */
    #[Computed]
    public function selectedSuppliersCount(): int
    {
        return count(array_unique(array_filter($this->itemSelections)));
    }

    /**
     * Check if we have any quotes to compare.
     */
    #[Computed]
    public function hasQuotes(): bool
    {
        return $this->quotes->isNotEmpty();
    }

    /**
     * Format a currency value using the team's base currency.
     */
    public function formatCurrency(float $value): string
    {
        /** @var \App\Models\Team|null $team */
        $team = filament()->getTenant();
        $currency = $team?->getBaseCurrency();

        if ($currency === null) {
            return number_format($value, 2);
        }

        return $currency->format($value);
    }

    public function render(): View
    {
        return view('livewire.supplier-quote-comparison');
    }
}
