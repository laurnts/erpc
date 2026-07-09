<?php

declare(strict_types=1);

namespace App\Observers;

use App\Data\TeamErpSettings;
use App\Enums\PrepaymentType;
use App\Enums\SupplierQuoteStatus;
use App\Models\QuotationEvaluation;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;

final readonly class SupplierQuoteObserver
{
    /**
     * Handle the SupplierQuote "creating" event.
     */
    public function creating(SupplierQuote $supplierQuote): void
    {
        $this->syncPrepaymentColumns($supplierQuote);
        // Only set team_id and creator_id if not already set
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($supplierQuote->creator_id === null) {
                $supplierQuote->creator_id = $user->getKey();
            }

            if ($supplierQuote->team_id === null && $user->currentTeam !== null) {
                $supplierQuote->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate quote number if not provided
        /** @var string|null $quoteNumber */
        $quoteNumber = $supplierQuote->quote_number;
        if ($quoteNumber === null || $quoteNumber === '') {
            $supplierQuote->quote_number = $this->generateQuoteNumber($supplierQuote);
        }

        // Set default validity if not provided
        if ($supplierQuote->valid_until === null && $supplierQuote->quoted_at !== null) {
            $team = $supplierQuote->team ?? ($supplierQuote->team_id !== null ? Team::find($supplierQuote->team_id) : null);
            $settings = $team?->getErpSettings() ?? new TeamErpSettings;
            $supplierQuote->valid_until = $supplierQuote->quoted_at->addDays($settings->quote_validity_days);
        }
    }

    /**
     * Handle the SupplierQuote "updating" event.
     */
    public function updating(SupplierQuote $supplierQuote): void
    {
        $this->syncPrepaymentColumns($supplierQuote);

        // Auto-change status when prices are inputted: SELECTED if obtained, otherwise RECEIVED
        if ($supplierQuote->status === SupplierQuoteStatus::PENDING) {
            // Check current total from database if quote exists
            if ($supplierQuote->exists) {
                $originalTotal = (float) $supplierQuote->getOriginal('total', 0);
                $newTotal = (float) $supplierQuote->total;

                // Use the higher of the two (in case items were just saved)
                $total = max($originalTotal, $newTotal);
            } else {
                $total = (float) $supplierQuote->total;
            }

            // Check if total is greater than 0 (meaning prices have been inputted)
            if ($total > 0) {
                $supplierQuote->status = $supplierQuote->obtained
                    ? SupplierQuoteStatus::SELECTED
                    : SupplierQuoteStatus::RECEIVED;
            }
        }
    }

    /**
     * Handle the SupplierQuote "updated" event.
     */
    public function updated(SupplierQuote $supplierQuote): void
    {
        // After update, check if status needs to be changed based on items
        // This handles cases where items were saved after the quote was saved
        if ($supplierQuote->status === SupplierQuoteStatus::PENDING) {
            // Reload to get fresh items and totals
            $supplierQuote->refresh();

            // Check if items have prices
            $hasPrices = $supplierQuote->items()->where('unit_price', '>', 0)->exists();
            $total = (float) $supplierQuote->total;

            if ($hasPrices || $total > 0) {
                $supplierQuote->status = $supplierQuote->obtained
                    ? SupplierQuoteStatus::SELECTED
                    : SupplierQuoteStatus::RECEIVED;
                $supplierQuote->saveQuietly();
            }
        }

        // Sync related QuotationEvaluations when quote status changes
        // This ensures QEs reflect the current active quotes (RECEIVED/SELECTED/REJECTED).
        // Outcome-only transitions (RECEIVED→REJECTED stamped by AnnounceSupplierRequestOutcomes)
        // are skipped: announcing outcomes must never re-sync snapshots or reset
        // an approved evaluation.
        if ($supplierQuote->wasChanged('status')
            && $supplierQuote->request_id !== null
            && ! $this->isAnnouncedOutcomeTransition($supplierQuote)) {
            $this->syncRelatedQuotationEvaluations($supplierQuote);
        }
    }

    /**
     * An outcome-only transition: the quote was just marked REJECTED while
     * carrying the outcomes_announced_at stamp (set in the same save by
     * AnnounceSupplierRequestOutcomes). Manual staff rejections (no stamp) still re-sync.
     */
    private function isAnnouncedOutcomeTransition(SupplierQuote $supplierQuote): bool
    {
        return $supplierQuote->status === SupplierQuoteStatus::REJECTED
            && $supplierQuote->outcomes_announced_at !== null;
    }

    /**
     * Handle the SupplierQuote "deleted" event.
     */
    public function deleted(SupplierQuote $supplierQuote): void
    {
        // Sync related QuotationEvaluations when quote is deleted
        // This ensures QEs no longer include deleted quotes
        if ($supplierQuote->request_id !== null) {
            $this->syncRelatedQuotationEvaluations($supplierQuote);
        }
    }

    /**
     * Sync related QuotationEvaluations when supplier quote changes.
     * Includes approved QEs which will be reset to pending status.
     */
    private function syncRelatedQuotationEvaluations(SupplierQuote $quote): void
    {
        if ($quote->request_id === null) {
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

    /**
     * Keep prepayment_percent and prepayment_amount in sync with prepayment_type.
     * When type is PERCENT, store the value in prepayment_percent; when AMOUNT, use prepayment_amount.
     */
    private function syncPrepaymentColumns(SupplierQuote $supplierQuote): void
    {
        if ($supplierQuote->prepayment_type === PrepaymentType::PERCENT) {
            $value = (int) $supplierQuote->prepayment_percent;
            if ($value === 0 && (float) $supplierQuote->prepayment_amount > 0) {
                $value = (int) round((float) $supplierQuote->prepayment_amount);
            }
            $supplierQuote->prepayment_percent = $value;
            $supplierQuote->prepayment_amount = '0.0000';
        } else {
            $supplierQuote->prepayment_percent = 0;
        }
    }

    /**
     * Generate a unique supplier quote number (SQ-YYYY-NNNN format).
     */
    private function generateQuoteNumber(SupplierQuote $supplierQuote): string
    {
        $prefix = 'SQ';
        $year = date('Y');
        $pattern = $prefix.'-'.$year.'-%';

        // Get the highest sequence number for this team and year
        $lastQuote = SupplierQuote::query()
            ->withTrashed()
            ->where('team_id', $supplierQuote->team_id)
            ->where('quote_number', 'like', $pattern)
            ->orderByDesc('quote_number')
            ->first();

        $nextNumber = 1;
        if ($lastQuote !== null) {
            $regex = '/^'.preg_quote($prefix, '/').'-'.$year.'-(\d+)$/';
            if (preg_match($regex, (string) $lastQuote->quote_number, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);
    }
}
