<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SupplierQuoteStatus;
use App\Filament\Resources\QuotationEvaluationResource;
use App\Livewire\Concerns\AuthorizesLivewireActions;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
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
    use AuthorizesLivewireActions;

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
        // Verify request belongs to current team
        $this->ensureTeamOwnership($request);

        $this->request = $request;
        $this->initializeSelections();
    }

    /**
     * Initialize selections from items marked as selected.
     */
    private function initializeSelections(): void
    {
        // Get items that are specifically marked as selected
        $selectedItems = SupplierQuoteItem::query()
            ->whereHas('supplierQuote', fn ($query) => $query->where('request_id', $this->request->getKey()))
            ->where('is_selected', true)
            ->whereNotNull('request_item_id')
            ->get();

        foreach ($selectedItems as $item) {
            $this->itemSelections[$item->request_item_id] = $item->supplier_quote_id;
        }
    }

    /**
     * Select a supplier for a specific item.
     */
    public function selectSupplierForItem(int $requestItemId, int $supplierQuoteId): void
    {
        // Validate that the supplier quote exists in our quotes collection
        if ($this->quotes->firstWhere('id', $supplierQuoteId) === null) {
            return;
        }

        // Validate that the request item exists in our request items collection
        if ($this->requestItems->firstWhere('id', $requestItemId) === null) {
            return;
        }

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
     * Apply the current selections by updating quote statuses and item selections.
     *
     * @throws AuthorizationException
     */
    public function applySelections(): void
    {
        // Authorize the action - user must be able to update the request
        Gate::authorize('update', $this->request);

        // Group selections by quote
        $quoteSelections = [];
        foreach ($this->itemSelections as $requestItemId => $quoteId) {
            if ($quoteId !== null) {
                $quoteSelections[$quoteId][] = $requestItemId;
            }
        }

        // First, clear all is_selected flags for this request's quotes
        SupplierQuoteItem::query()
            ->whereHas('supplierQuote', fn ($query) => $query->where('request_id', $this->request->getKey()))
            ->update(['is_selected' => false]);

        // Mark selected quotes and their specific items
        foreach ($this->quotes as $quote) {
            if (array_key_exists($quote->getKey(), $quoteSelections)) {
                $quote->markAsSelected();

                // Mark the specific items as selected
                SupplierQuoteItem::query()
                    ->where('supplier_quote_id', $quote->getKey())
                    ->whereIn('request_item_id', $quoteSelections[$quote->getKey()])
                    ->update(['is_selected' => true]);
            } elseif ($quote->status === SupplierQuoteStatus::SELECTED) {
                // Quote was selected but no longer has selections, mark as received
                $quote->status = SupplierQuoteStatus::RECEIVED;
                $quote->save();
            }
        }

        // Manually sync QE snapshot data since bulk updates don't trigger model events
        // This ensures the QE view page shows the correct selected items
        $quotationEvaluations = QuotationEvaluation::query()
            ->where('request_id', $this->request->getKey())
            ->get();

        foreach ($quotationEvaluations as $qe) {
            $qe->syncSnapshotData();
        }

        Notification::make()
            ->title('Selections applied')
            ->body('Quote statuses have been updated.')
            ->success()
            ->send();

        // Trigger a Livewire event that Alpine.js can listen to
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
            ->whereIn('status', [SupplierQuoteStatus::RECEIVED, SupplierQuoteStatus::SELECTED])
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
     * Only considers items with actual prices (> 0).
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

                // Only consider prices greater than 0
                if ($unitPriceBase > 0 && ($bestUnitPriceBase === null || $unitPriceBase < $bestUnitPriceBase)) {
                    $bestUnitPriceBase = $unitPriceBase;
                    $bestQuoteId = $quoteId;
                }
            }

            $bestPrices[$requestItemId] = $bestQuoteId;
        }

        return $bestPrices;
    }

    /**
     * Check if all items have prices entered from all suppliers.
     * Returns true only if:
     * 1. Every request item has at least one quote item with a price > 0
     * 2. Every supplier that has quoted items has prices > 0 for all their quoted items
     */
    #[Computed]
    public function hasPricesEntered(): bool
    {
        // If no request items or quotes, return false
        if ($this->requestItems->isEmpty() || $this->quotes->isEmpty()) {
            return false;
        }

        // First check: Every request item must have at least one quote item with price > 0
        foreach ($this->priceMatrix as $quoteItems) {
            $hasPriceForItem = false;

            foreach ($quoteItems as $quoteItem) {
                if ($quoteItem !== null) {
                    $unitPrice = (float) $quoteItem->unit_price_exc_tax;
                    if ($unitPrice > 0) {
                        $hasPriceForItem = true;
                        break;
                    }
                }
            }

            // If any item doesn't have a price from any supplier, return false
            if (! $hasPriceForItem) {
                return false;
            }
        }

        // Second check: Every supplier must have prices > 0 for all items they've quoted
        foreach ($this->quotes as $quote) {
            foreach ($this->requestItems as $requestItem) {
                $quoteItem = $this->priceMatrix[$requestItem->getKey()][$quote->getKey()] ?? null;

                // If supplier has quoted this item, it must have a price > 0
                if ($quoteItem !== null) {
                    $unitPrice = (float) $quoteItem->unit_price_exc_tax;
                    if ($unitPrice <= 0) {
                        return false;
                    }
                }
            }
        }

        return true;
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
     * Check if QE exists for this request.
     */
    #[Computed]
    public function hasQuotationEvaluation(): bool
    {
        return $this->request->quotationEvaluations()->exists();
    }

    /**
     * Get the latest QE for this request.
     */
    #[Computed]
    public function latestQuotationEvaluation(): ?QuotationEvaluation
    {
        return $this->request->quotationEvaluations()->latest()->first();
    }

    /**
     * Redirect to view QE page.
     */
    public function viewQuotationEvaluation(): void
    {
        $qe = $this->latestQuotationEvaluation;
        if ($qe === null) {
            return;
        }

        $this->redirect(QuotationEvaluationResource::getUrl('view', ['record' => $qe]));
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
