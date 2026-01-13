<?php

declare(strict_types=1);

namespace App\Notifications\Erp;

use App\Models\BuyerInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification for overdue invoices.
 *
 * Sent to the invoice creator when an invoice becomes overdue.
 * Includes invoice number, buyer/supplier name, amount outstanding, and days overdue.
 */
final class InvoiceOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly BuyerInvoice $invoice
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
        $buyer = $this->invoice->buyerOrder?->buyer;
        $daysOverdue = $this->invoice->days_overdue;

        return [
            'type' => 'invoice_overdue',
            'invoice_id' => $this->invoice->getKey(),
            'invoice_number' => $this->invoice->invoice_number,
            'buyer_name' => $buyer?->name ?? 'Unknown Buyer',
            'buyer_id' => $buyer?->getKey(),
            'total' => (float) $this->invoice->total,
            'amount_outstanding' => $this->invoice->amount_outstanding,
            'amount_paid' => (float) $this->invoice->amount_paid,
            'due_date' => $this->invoice->due_at?->toDateString(),
            'days_overdue' => $daysOverdue,
            'urgency' => $this->getUrgencyLevel($daysOverdue),
            'title' => $this->getTitle($daysOverdue),
            'message' => $this->getMessage($buyer?->name ?? 'Unknown Buyer', $daysOverdue),
            'icon' => 'heroicon-o-exclamation-triangle',
            'color' => $this->getColor($daysOverdue),
            'action_url' => $this->getActionUrl(),
            'currency_code' => $this->invoice->currency?->code ?? 'USD',
        ];
    }

    /**
     * Get the urgency level based on days overdue.
     */
    private function getUrgencyLevel(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue >= 30 => 'critical',
            $daysOverdue >= 14 => 'high',
            $daysOverdue >= 7 => 'medium',
            default => 'low',
        };
    }

    /**
     * Get the notification title.
     */
    private function getTitle(int $daysOverdue): string
    {
        $formattedAmount = number_format($this->invoice->amount_outstanding, 2);
        $currencyCode = $this->invoice->currency?->code ?? 'USD';

        return match (true) {
            $daysOverdue === 1 => "Invoice {$this->invoice->invoice_number} is 1 day overdue ({$currencyCode} {$formattedAmount})",
            default => "Invoice {$this->invoice->invoice_number} is {$daysOverdue} days overdue ({$currencyCode} {$formattedAmount})",
        };
    }

    /**
     * Get the notification message.
     */
    private function getMessage(string $buyerName, int $daysOverdue): string
    {
        $formattedAmount = number_format($this->invoice->amount_outstanding, 2);
        $currencyCode = $this->invoice->currency?->code ?? 'USD';
        $dueDate = $this->invoice->due_at?->format('M j, Y') ?? 'Unknown';

        $baseMessage = "Invoice {$this->invoice->invoice_number} for {$buyerName} was due on {$dueDate}. ";

        if ($this->invoice->amount_paid > 0) {
            $paidAmount = number_format((float) $this->invoice->amount_paid, 2);
            $baseMessage .= "Partial payment of {$currencyCode} {$paidAmount} received. ";
        }

        $baseMessage .= "Outstanding amount: {$currencyCode} {$formattedAmount}.";

        if ($daysOverdue >= 30) {
            $baseMessage .= ' Urgent follow-up required.';
        } elseif ($daysOverdue >= 14) {
            $baseMessage .= ' Consider escalating collection efforts.';
        }

        return $baseMessage;
    }

    /**
     * Get the color for the notification.
     */
    private function getColor(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue >= 14 => 'danger',
            $daysOverdue >= 7 => 'warning',
            default => 'warning',
        };
    }

    /**
     * Get the action URL for the notification.
     */
    private function getActionUrl(): ?string
    {
        try {
            return route('filament.app.resources.buyer-invoices.view', ['record' => $this->invoice->getKey()]);
        } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException) {
            // Route may not exist if resource not yet created
            return null;
        }
    }
}
