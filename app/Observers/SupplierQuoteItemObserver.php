<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SupplierQuoteStatus;
use App\Models\QuotationEvaluation;
use App\Models\SupplierQuoteItem;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;

final readonly class SupplierQuoteItemObserver
{
    /**
     * Handle the SupplierQuoteItem "creating" event.
     */
    public function creating(SupplierQuoteItem $item): void
    {
        $this->prefillTaxCodeFromArticle($item);
        $this->syncTaxRateFromCode($item);
        $this->syncUnitFromUnitOfMeasure($item);
        $item->calculateTotals();
    }

    /**
     * Handle the SupplierQuoteItem "updating" event.
     */
    public function updating(SupplierQuoteItem $item): void
    {
        // Re-sync tax rate if tax code changed
        if ($item->isDirty('tax_code_id')) {
            $this->syncTaxRateFromCode($item);
        }

        // Re-sync unit if unit_of_measure_id changed
        if ($item->isDirty('unit_of_measure_id')) {
            $this->syncUnitFromUnitOfMeasure($item);
        }

        // Recalculate if pricing fields changed
        if ($item->isDirty(['quantity', 'unit_price', 'tax_code_id', 'is_tax_inclusive', 'tax_rate'])) {
            $item->calculateTotals();
        }
    }

    /**
     * Handle the SupplierQuoteItem "created" event.
     */
    public function created(SupplierQuoteItem $item): void
    {
        $this->recalculateQuoteTotals($item);
    }

    /**
     * Handle the SupplierQuoteItem "updated" event.
     */
    public function updated(SupplierQuoteItem $item): void
    {
        // Recalculate quote header totals when line totals change
        if ($item->wasChanged(['line_subtotal', 'line_tax', 'line_total'])) {
            $this->recalculateQuoteTotals($item);
        }

        // Sync related QuotationEvaluations when unit price or related pricing fields change
        if ($item->wasChanged(['unit_price', 'unit_price_exc_tax', 'line_subtotal', 'line_tax', 'line_total', 'is_selected'])) {
            $this->syncRelatedQuotationEvaluations($item);
        }
    }

    /**
     * Handle the SupplierQuoteItem "deleted" event.
     */
    public function deleted(SupplierQuoteItem $item): void
    {
        $this->recalculateQuoteTotals($item);
    }

    /**
     * Prefill tax code from the article's default tax code if not already set.
     */
    private function prefillTaxCodeFromArticle(SupplierQuoteItem $item): void
    {
        // Check if supplier is taxable - skip tax code prefilling if not taxable
        $supplier = $item->supplierQuote?->supplier;
        $isSupplierTaxable = $supplier?->is_taxable ?? true; // Default to taxable if supplier not found

        if (! $isSupplierTaxable) {
            return;
        }

        // Only prefill if tax_code_id is not set and article_id is set
        if ($item->tax_code_id === null && $item->article_id !== null) {
            $article = $item->article;
            if ($article !== null && $article->default_tax_code_id !== null) {
                $item->tax_code_id = $article->default_tax_code_id;
            }
        }
    }

    /**
     * Sync tax rate from the selected tax code.
     */
    private function syncTaxRateFromCode(SupplierQuoteItem $item): void
    {
        // Check if supplier is taxable - skip tax rate syncing if not taxable
        $supplier = $item->supplierQuote?->supplier;
        $isSupplierTaxable = $supplier?->is_taxable ?? true; // Default to taxable if supplier not found

        if (! $isSupplierTaxable) {
            // Clear tax values for non-taxable suppliers
            $item->tax_code_id = null;
            $item->tax_rate = '0.0000';
            $item->is_tax_inclusive = false;

            return;
        }

        if ($item->tax_code_id !== null) {
            $taxCode = TaxCode::find($item->tax_code_id);
            if ($taxCode !== null) {
                $item->tax_rate = (string) $taxCode->rate;

                // Use tax code's default inclusivity if item's inclusivity hasn't been explicitly set
                if (! $item->isDirty('is_tax_inclusive') && $item->getOriginal('is_tax_inclusive') === null) {
                    $item->is_tax_inclusive = $taxCode->is_inclusive_default;
                }
            }
        }
    }

    /**
     * Sync unit field from unit_of_measure_id if unit is not set.
     */
    private function syncUnitFromUnitOfMeasure(SupplierQuoteItem $item): void
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
     * Recalculate the parent quote's totals.
     */
    private function recalculateQuoteTotals(SupplierQuoteItem $item): void
    {
        $quote = $item->supplierQuote;
        if ($quote !== null) {
            $quote->recalculateTotals();

            // After recalculating totals, check if status needs to be updated
            // Reload to get fresh totals
            $quote->refresh();
            if ($quote->status === SupplierQuoteStatus::PENDING && (float) $quote->total > 0) {
                $quote->status = $quote->obtained
                    ? SupplierQuoteStatus::SELECTED
                    : SupplierQuoteStatus::RECEIVED;
                $quote->saveQuietly();
            }
        }
    }

    /**
     * Sync related QuotationEvaluations when supplier quote item prices change.
     * Includes approved QEs which will be reset to pending status.
     */
    private function syncRelatedQuotationEvaluations(SupplierQuoteItem $item): void
    {
        $quote = $item->supplierQuote;
        if ($quote === null || $quote->request_id === null) {
            return;
        }

        // Find all QuotationEvaluations for this request
        // Approved QEs will be reset to pending when synced
        $quotationEvaluations = QuotationEvaluation::query()
            ->where('request_id', $quote->request_id)
            ->get();

        // Sync each QE's snapshot data
        foreach ($quotationEvaluations as $qe) {
            $qe->syncSnapshotData();
        }
    }
}
