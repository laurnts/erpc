<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BuyerQuoteItem;
use App\Models\UnitOfMeasure;

final readonly class BuyerQuoteItemObserver
{
    /**
     * Handle the BuyerQuoteItem "creating" event.
     */
    public function creating(BuyerQuoteItem $item): void
    {
        // Prefill tax code from article's default if not set
        $this->prefillTaxCodeFromArticle($item);

        // Sync unit from unit_of_measure_id
        $this->syncUnitFromUnitOfMeasure($item);

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

        // Re-sync unit if unit_of_measure_id changed
        if ($item->isDirty('unit_of_measure_id')) {
            $this->syncUnitFromUnitOfMeasure($item);
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
     * Prefill tax code from the article's default tax code if not already set.
     */
    private function prefillTaxCodeFromArticle(BuyerQuoteItem $item): void
    {
        // Only prefill if tax_code_id is not set and article_id is set
        if ($item->tax_code_id === null && $item->article_id !== null) {
            $article = $item->article;
            if ($article !== null && $article->default_tax_code_id !== null) {
                $item->tax_code_id = $article->default_tax_code_id;
            }
        }
    }

    /**
     * Sync unit field from unit_of_measure_id if unit is not set.
     */
    private function syncUnitFromUnitOfMeasure(BuyerQuoteItem $item): void
    {
        // If unit is not set but unit_of_measure_id is set, populate unit from UnitOfMeasure
        if ($item->unit_of_measure_id !== null) {
            // Check if unit is already set in raw attributes
            $attributes = $item->getAttributes();
            if (! isset($attributes['unit']) || $attributes['unit'] === null) {
                $unitOfMeasure = UnitOfMeasure::find($item->unit_of_measure_id);
                if ($unitOfMeasure !== null) {
                    // Set the raw attributes by merging with existing attributes
                    $item->setRawAttributes(array_merge($item->getAttributes(), ['unit' => $unitOfMeasure->code]));
                }
            }
        }
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
