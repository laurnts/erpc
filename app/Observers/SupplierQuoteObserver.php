<?php

declare(strict_types=1);

namespace App\Observers;

use App\Data\TeamErpSettings;
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
