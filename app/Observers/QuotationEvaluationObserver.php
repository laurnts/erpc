<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\QuotationEvaluation;
use App\Models\User;

final readonly class QuotationEvaluationObserver
{
    /**
     * Handle the QuotationEvaluation "creating" event.
     */
    public function creating(QuotationEvaluation $quotationEvaluation): void
    {
        // Only set team_id and creator_id if not already set and user is authenticated
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($quotationEvaluation->creator_id === null) {
                $quotationEvaluation->creator_id = $user->getKey();
            }

            if ($quotationEvaluation->team_id === null && $user->currentTeam !== null) {
                $quotationEvaluation->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate QE number if not provided
        /** @var string|null $qeNumber */
        $qeNumber = $quotationEvaluation->qe_number;
        if (($qeNumber === null || $qeNumber === '') && $quotationEvaluation->team_id !== null) {
            $quotationEvaluation->qe_number = QuotationEvaluation::generateQeNumber($quotationEvaluation->team_id);
        }
    }
}
