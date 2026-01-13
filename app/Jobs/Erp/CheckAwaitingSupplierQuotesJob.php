<?php

declare(strict_types=1);

namespace App\Jobs\Erp;

use App\Enums\SupplierQuoteStatus;
use App\Models\SupplierQuote;
use App\Notifications\Erp\AwaitingSupplierQuoteNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Job to check for supplier quotes awaiting response for too long.
 *
 * Checks for quotes in PENDING status that have been waiting for more than
 * a configurable number of days. Sends reminder notifications to the creator.
 */
final class CheckAwaitingSupplierQuotesJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * Default threshold in days for awaiting quotes.
     */
    private const int DEFAULT_THRESHOLD_DAYS = 7;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $thresholdDays = self::DEFAULT_THRESHOLD_DAYS
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $thresholdDate = Carbon::today()->subDays($this->thresholdDays);

        // Find supplier quotes that have been in PENDING status for too long
        $quotes = SupplierQuote::query()
            ->with(['supplier', 'creator', 'request'])
            ->where('status', SupplierQuoteStatus::PENDING)
            ->where('created_at', '<=', $thresholdDate)
            ->get();

        /** @var SupplierQuote $quote */
        foreach ($quotes as $quote) {
            $this->processAwaitingQuote($quote);
        }
    }

    /**
     * Process a single awaiting quote.
     */
    private function processAwaitingQuote(SupplierQuote $quote): void
    {
        // Check if we've already notified for this period
        $lastNotifiedAt = $this->getLastNotifiedAt($quote);
        $daysSinceLastNotification = $lastNotifiedAt !== null
            ? Carbon::parse($lastNotifiedAt)->diffInDays(now())
            : null;

        // Don't notify more than once per week
        if ($daysSinceLastNotification !== null && $daysSinceLastNotification < 7) {
            return;
        }

        $this->sendNotification($quote);
        $this->markAsNotified($quote);
    }

    /**
     * Send notification to the quote creator.
     */
    private function sendNotification(SupplierQuote $quote): void
    {
        $creator = $quote->creator;
        if ($creator === null) {
            return;
        }

        $daysWaiting = (int) $quote->created_at->diffInDays(now());
        $creator->notify(new AwaitingSupplierQuoteNotification($quote, $daysWaiting));
    }

    /**
     * Get the last notification timestamp.
     */
    private function getLastNotifiedAt(SupplierQuote $quote): ?string
    {
        /** @var array<string, mixed>|null $metadata */
        $metadata = $quote->getAttributeValue('notification_metadata');

        if ($metadata === null || ! is_array($metadata)) {
            return null;
        }

        return $metadata['awaiting_notified_at'] ?? null;
    }

    /**
     * Mark the quote as notified.
     */
    private function markAsNotified(SupplierQuote $quote): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $quote->getAttributeValue('notification_metadata') ?? [];

        $notificationCount = ($metadata['awaiting_notification_count'] ?? 0) + 1;

        $metadata['awaiting_notified'] = true;
        $metadata['awaiting_notified_at'] = now()->toIso8601String();
        $metadata['awaiting_notification_count'] = $notificationCount;

        $quote->forceFill(['notification_metadata' => $metadata]);
        $quote->saveQuietly();
    }
}
