<?php

declare(strict_types=1);

namespace App\Jobs\Erp;

use App\Enums\BuyerQuoteStatus;
use App\Models\BuyerQuote;
use App\Notifications\Erp\QuoteExpirationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Job to check for expiring buyer quotes and send notifications.
 *
 * Checks for quotes expiring in 7 days, 3 days, and 1 day.
 * Only notifies once per threshold using metadata tracking.
 */
final class CheckExpiringQuotesJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * Expiration thresholds in days.
     *
     * @var list<int>
     */
    private const array THRESHOLDS = [7, 3, 1];

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach (self::THRESHOLDS as $threshold) {
            $this->processThreshold($threshold);
        }
    }

    /**
     * Process a specific expiration threshold.
     */
    private function processThreshold(int $daysUntilExpiry): void
    {
        $targetDate = Carbon::today()->addDays($daysUntilExpiry);

        // Find quotes expiring on the target date
        // Only consider SENT status quotes (not draft, accepted, rejected, etc.)
        $quotes = BuyerQuote::query()
            ->with(['buyer', 'creator'])
            ->where('status', BuyerQuoteStatus::SENT)
            ->whereDate('valid_until', $targetDate)
            ->get();

        /** @var BuyerQuote $quote */
        foreach ($quotes as $quote) {
            $this->notifyIfNotAlreadyNotified($quote, $daysUntilExpiry);
        }
    }

    /**
     * Send notification if not already sent for this threshold.
     */
    private function notifyIfNotAlreadyNotified(BuyerQuote $quote, int $daysUntilExpiry): void
    {
        $metadataKey = $this->getMetadataKey($daysUntilExpiry);

        // Check if we've already notified for this threshold
        if ($this->hasBeenNotified($quote, $metadataKey)) {
            return;
        }

        $creator = $quote->creator;
        if ($creator === null) {
            return;
        }

        // Send notification
        $creator->notify(new QuoteExpirationNotification($quote, $daysUntilExpiry));

        // Mark as notified
        $this->markAsNotified($quote, $metadataKey);
    }

    /**
     * Get metadata key for the notification threshold.
     */
    private function getMetadataKey(int $daysUntilExpiry): string
    {
        return "expiry_notified_{$daysUntilExpiry}_days";
    }

    /**
     * Check if notification has already been sent for this threshold.
     */
    private function hasBeenNotified(BuyerQuote $quote, string $metadataKey): bool
    {
        /** @var Collection<string, mixed>|null $customFields */
        $customFields = $quote->getAttributeValue('notification_metadata');

        if ($customFields === null) {
            return false;
        }

        if (is_array($customFields)) {
            return isset($customFields[$metadataKey]) && $customFields[$metadataKey] === true;
        }

        return false;
    }

    /**
     * Mark the quote as notified for this threshold.
     */
    private function markAsNotified(BuyerQuote $quote, string $metadataKey): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $quote->getAttributeValue('notification_metadata') ?? [];

        $metadata[$metadataKey] = true;
        $metadata["{$metadataKey}_at"] = now()->toIso8601String();

        $quote->forceFill(['notification_metadata' => $metadata]);
        $quote->saveQuietly();
    }
}
