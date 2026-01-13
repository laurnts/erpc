<?php

declare(strict_types=1);

namespace App\Jobs\Erp;

use App\Enums\InvoiceStatus;
use App\Models\BuyerInvoice;
use App\Notifications\Erp\InvoiceOverdueNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Job to check for overdue invoices and send notifications.
 *
 * Checks for invoices where due_at < now() and status is not paid/cancelled.
 * Updates status to OVERDUE and sends notification to the invoice creator.
 * Only notifies once per invoice using metadata tracking.
 */
final class CheckOverdueInvoicesJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Find invoices that are overdue but not yet marked as such
        // Only process SENT or PARTIAL status invoices
        $invoices = BuyerInvoice::query()
            ->with(['creator', 'buyerOrder.buyer', 'request'])
            ->whereIn('status', [InvoiceStatus::SENT, InvoiceStatus::PARTIAL])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->startOfDay())
            ->get();

        /** @var BuyerInvoice $invoice */
        foreach ($invoices as $invoice) {
            $this->processOverdueInvoice($invoice);
        }
    }

    /**
     * Process a single overdue invoice.
     */
    private function processOverdueInvoice(BuyerInvoice $invoice): void
    {
        $wasAlreadyOverdue = $invoice->status === InvoiceStatus::OVERDUE;

        // Update status to OVERDUE if not already
        if ($invoice->status !== InvoiceStatus::OVERDUE) {
            $invoice->status = InvoiceStatus::OVERDUE;
            $invoice->saveQuietly();
        }

        // Only notify if we haven't notified before
        if (! $this->hasBeenNotified($invoice)) {
            $this->sendNotification($invoice);
            $this->markAsNotified($invoice);
        }
    }

    /**
     * Send notification to the invoice creator.
     */
    private function sendNotification(BuyerInvoice $invoice): void
    {
        $creator = $invoice->creator;
        if ($creator === null) {
            return;
        }

        $creator->notify(new InvoiceOverdueNotification($invoice));
    }

    /**
     * Check if notification has already been sent.
     */
    private function hasBeenNotified(BuyerInvoice $invoice): bool
    {
        /** @var array<string, mixed>|null $metadata */
        $metadata = $invoice->getAttributeValue('notification_metadata');

        if ($metadata === null || ! is_array($metadata)) {
            return false;
        }

        return isset($metadata['overdue_notified']) && $metadata['overdue_notified'] === true;
    }

    /**
     * Mark the invoice as notified.
     */
    private function markAsNotified(BuyerInvoice $invoice): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $invoice->getAttributeValue('notification_metadata') ?? [];

        $metadata['overdue_notified'] = true;
        $metadata['overdue_notified_at'] = now()->toIso8601String();

        $invoice->forceFill(['notification_metadata' => $metadata]);
        $invoice->saveQuietly();
    }
}
