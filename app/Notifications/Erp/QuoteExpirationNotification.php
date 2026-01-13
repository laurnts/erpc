<?php

declare(strict_types=1);

namespace App\Notifications\Erp;

use App\Models\BuyerQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification for expiring buyer quotes.
 *
 * Sent to the quote creator when a quote is about to expire.
 * Different urgency levels based on days remaining.
 */
final class QuoteExpirationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly BuyerQuote $quote,
        public readonly int $daysUntilExpiry
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
            'type' => 'quote_expiration',
            'quote_id' => $this->quote->getKey(),
            'quote_number' => $this->quote->quote_number,
            'buyer_name' => $this->quote->buyer?->name ?? 'Unknown Buyer',
            'buyer_id' => $this->quote->buyer_id,
            'expiry_date' => $this->quote->valid_until?->toDateString(),
            'days_until_expiry' => $this->daysUntilExpiry,
            'urgency' => $this->getUrgencyLevel(),
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'action_url' => $this->getActionUrl(),
        ];
    }

    /**
     * Get the urgency level based on days remaining.
     */
    private function getUrgencyLevel(): string
    {
        return match (true) {
            $this->daysUntilExpiry <= 1 => 'critical',
            $this->daysUntilExpiry <= 3 => 'high',
            default => 'medium',
        };
    }

    /**
     * Get the notification title.
     */
    private function getTitle(): string
    {
        $buyerName = $this->quote->buyer?->name ?? 'Unknown Buyer';

        return match ($this->daysUntilExpiry) {
            1 => "Quote {$this->quote->quote_number} expires tomorrow",
            0 => "Quote {$this->quote->quote_number} expires today",
            default => "Quote {$this->quote->quote_number} expires in {$this->daysUntilExpiry} days",
        };
    }

    /**
     * Get the notification message.
     */
    private function getMessage(): string
    {
        $buyerName = $this->quote->buyer?->name ?? 'Unknown Buyer';
        $expiryDate = $this->quote->valid_until?->format('M j, Y') ?? 'Unknown';

        return match ($this->daysUntilExpiry) {
            1 => "Your quote {$this->quote->quote_number} for {$buyerName} will expire tomorrow ({$expiryDate}). Consider following up with the buyer or extending the validity.",
            0 => "Your quote {$this->quote->quote_number} for {$buyerName} expires today ({$expiryDate}). Take action now to avoid losing this opportunity.",
            default => "Your quote {$this->quote->quote_number} for {$buyerName} will expire on {$expiryDate} ({$this->daysUntilExpiry} days remaining).",
        };
    }

    /**
     * Get the icon for the notification.
     */
    private function getIcon(): string
    {
        return match ($this->getUrgencyLevel()) {
            'critical' => 'heroicon-o-exclamation-circle',
            'high' => 'heroicon-o-exclamation-triangle',
            default => 'heroicon-o-clock',
        };
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
            return route('filament.app.resources.buyer-quotes.view', ['record' => $this->quote->getKey()]);
        } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException) {
            // Route may not exist if resource not yet created
            return null;
        }
    }
}
