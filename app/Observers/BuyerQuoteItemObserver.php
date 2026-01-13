<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BuyerQuoteItem;

final readonly class BuyerQuoteItemObserver
{
    /**
     * Handle the BuyerQuoteItem "creating" event.
     */
    public function creating(BuyerQuoteItem $item): void
    {
        // Update tax rate from tax code if set
        $item->updateTaxRateFromCode();

        // Recalculate prices
        $item->recalculatePrices();
    }

    /**
     * Handle the BuyerQuoteItem "updating" event.
     */
    public function updating(BuyerQuoteItem $item): void
    {
        // If tax code changed, update the rate
        if ($item->isDirty('tax_code_id')) {
            $item->updateTaxRateFromCode();
        }

        // Recalculate prices if relevant fields changed
        $priceFields = ['quantity', 'unit_price', 'cost_price', 'tax_rate', 'is_tax_inclusive', 'tax_code_id'];
        if ($item->isDirty($priceFields)) {
            $item->recalculatePrices();
        }
    }

    /**
     * Handle the BuyerQuoteItem "created" event.
     */
    public function created(BuyerQuoteItem $item): void
    {
        $this->recalculateQuoteTotals($item);
    }

    /**
     * Handle the BuyerQuoteItem "updated" event.
     */
    public function updated(BuyerQuoteItem $item): void
    {
        $this->recalculateQuoteTotals($item);
    }

    /**
     * Handle the BuyerQuoteItem "deleted" event.
     */
    public function deleted(BuyerQuoteItem $item): void
    {
        $this->recalculateQuoteTotals($item);
    }

    /**
     * Recalculate the parent quote totals.
     */
    private function recalculateQuoteTotals(BuyerQuoteItem $item): void
    {
        // Load the relationship fresh from the database
        $quote = \App\Models\BuyerQuote::find($item->buyer_quote_id);
        if ($quote !== null) {
            $quote->recalculateTotals();
        }
    }
}
