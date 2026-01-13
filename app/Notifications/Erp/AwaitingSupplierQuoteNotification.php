<?php

declare(strict_types=1);

namespace App\Notifications\Erp;

use App\Models\SupplierQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification for supplier quotes awaiting response for too long.
 *
 * Sent to the quote creator as a reminder to follow up with the supplier.
 */
final class AwaitingSupplierQuoteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly SupplierQuote $quote,
        public readonly int $daysWaiting
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'awaiting_supplier_quote',
            'quote_id' => $this->quote->getKey(),
            'quote_number' => $this->quote->quote_number,
            'supplier_name' => $this->quote->supplier?->name ?? 'Unknown Supplier',
            'supplier_id' => $this->quote->supplier_id,
            'request_id' => $this->quote->request_id,
            'request_number' => $this->quote->request?->request_number ?? null,
            'days_waiting' => $this->daysWaiting,
            'created_at' => $this->quote->created_at?->toDateString(),
            'urgency' => $this->getUrgencyLevel(),
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
            'icon' => 'heroicon-o-clock',
            'color' => $this->getColor(),
            'action_url' => $this->getActionUrl(),
        ];
    }

    /**
     * Get the urgency level based on days waiting.
     */
    private function getUrgencyLevel(): string
    {
        return match (true) {
            $this->daysWaiting >= 21 => 'critical',
            $this->daysWaiting >= 14 => 'high',
            default => 'medium',
        };
    }

    /**
     * Get the notification title.
     */
    private function getTitle(): string
    {
        $supplierName = $this->quote->supplier?->name ?? 'Unknown Supplier';

        return "Supplier quote {$this->quote->quote_number} awaiting response for {$this->daysWaiting} days";
    }

    /**
     * Get the notification message.
     */
    private function getMessage(): string
    {
        $supplierName = $this->quote->supplier?->name ?? 'Unknown Supplier';
        $requestNumber = $this->quote->request?->request_number;
        $createdDate = $this->quote->created_at?->format('M j, Y') ?? 'Unknown';

        $message = "Quote request {$this->quote->quote_number} sent to {$supplierName} on {$createdDate} ";
        $message .= "has been awaiting response for {$this->daysWaiting} days.";

        if ($requestNumber !== null) {
            $message .= " Related to request {$requestNumber}.";
        }

        if ($this->daysWaiting >= 21) {
            $message .= ' Consider contacting the supplier or seeking alternative sources.';
        } elseif ($this->daysWaiting >= 14) {
            $message .= ' Follow-up with the supplier recommended.';
        }

        return $message;
    }

    /**
     * Get the color for the notification.
     */
    private function getColor(): string
    {
        return match ($this->getUrgencyLevel()) {
            'critical' => 'danger',
            'high' => 'warning',
            default => 'info',
        };
    }

    /**
     * Get the action URL for the notification.
     */
    private function getActionUrl(): ?string
    {
        try {
            return route('filament.app.resources.supplier-quotes.view', ['record' => $this->quote->getKey()]);
        } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException) {
            // Route may not exist if resource not yet created
            return null;
        }
    }
}
