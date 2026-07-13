<?php

declare(strict_types=1);

namespace App\Actions\SupplierPortal;

use App\Enums\PortalType;
use App\Enums\SupplierQuoteStatus;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Notifications\SupplierQuoteOutcomeNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Terminal-event outcome announcement for a request's evaluation round.
 *
 * Selection during evaluation stays fully reversible and invisible to
 * suppliers; this action is the single point where outcomes become real:
 * sibling RECEIVED quotes with zero selected items are marked REJECTED via
 * the existing markAsRejected(), every participating quote is stamped with
 * outcomes_announced_at (which locks further applySelections() for the
 * round), and each affected supplier's portal users receive exactly one
 * won/lost notification — only for quotes actually sent to the supplier, so
 * internally-entered quotes never trigger supplier-facing mail.
 */
final readonly class AnnounceSupplierRequestOutcomes
{
    /**
     * @return array{winners: int, losers: int}|null null when the round is
     *                                               already announced or there is nothing to announce
     */
    public function execute(Request $request): ?array
    {
        if ($request->supplierRequestOutcomesAnnounced()) {
            return null;
        }

        $quotes = $request->supplierQuotes()
            ->whereIn('status', [SupplierQuoteStatus::RECEIVED, SupplierQuoteStatus::SELECTED])
            ->whereNull('declined_at')
            ->withCount([
                'items as selected_items_count' => fn (Builder $query) => $query->where('is_selected', true),
            ])
            ->get();

        if ($quotes->isEmpty()) {
            return null;
        }

        $now = now();
        $winners = 0;
        $losers = 0;

        DB::transaction(function () use ($quotes, $now, &$winners, &$losers): void {
            foreach ($quotes as $quote) {
                $quote->outcomes_announced_at = $now;

                $isLoser = $quote->status === SupplierQuoteStatus::RECEIVED
                    && (int) $quote->getAttribute('selected_items_count') === 0;

                if ($isLoser) {
                    // Single save carrying both the stamp and the REJECTED
                    // transition; the SupplierQuoteObserver recognizes this as
                    // an outcome-only transition and skips QE re-sync, so an
                    // approved evaluation is never reset by the announcement.
                    $quote->markAsRejected();
                    $losers++;
                } else {
                    $quote->saveQuietly();
                    $winners++;
                }
            }
        });

        foreach ($quotes as $quote) {
            $this->notifySupplierPortalUsers($quote);
        }

        return ['winners' => $winners, 'losers' => $losers];
    }

    /**
     * One outcome notification per supplier portal user, and only for quotes
     * the supplier actually received — an unsent internally-entered quote must
     * never surface sourcing activity staff never issued.
     */
    private function notifySupplierPortalUsers(SupplierQuote $quote): void
    {
        if ($quote->sent_to_supplier_at === null) {
            return;
        }

        $recipients = User::query()
            ->whereHas('portalMemberships', fn (Builder $query) => $query
                ->where('company_id', $quote->supplier_id)
                ->where('portal', PortalType::Supplier)
                ->where('is_active', true))
            ->get()
            ->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        $won = $quote->status !== SupplierQuoteStatus::REJECTED;

        Notification::send($recipients, new SupplierQuoteOutcomeNotification($quote, $won));
    }
}
