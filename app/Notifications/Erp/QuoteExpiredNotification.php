<?php

declare(strict_types=1);

namespace App\Notifications\Erp;

use App\Filament\Resources\RequestResource;
use App\Mail\Erp\QuoteExpiredMail;
use App\Models\BuyerQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification when a buyer quote has expired.
 * Sent to key accounts (and optionally in-app). Email is sent via QuoteExpiredMail.
 */
final class QuoteExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly BuyerQuote $quote
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $validUntil = $this->quote->valid_until?->format('M j, Y') ?? '—';
        $buyerName = $this->quote->buyer->name ?? 'Unknown Buyer';

        return [
            'type' => 'quote_expired',
            'quote_id' => $this->quote->getKey(),
            'quote_number' => $this->quote->quote_number,
            'buyer_name' => $buyerName,
            'buyer_id' => $this->quote->buyer_id,
            'valid_until' => $this->quote->valid_until?->toDateString(),
            'title' => "Quote {$this->quote->quote_number} has expired",
            'message' => "Quote {$this->quote->quote_number} for {$buyerName} expired (valid until was {$validUntil}).",
            'icon' => 'heroicon-o-clock',
            'color' => 'danger',
            'action_url' => $this->getActionUrl(),
        ];
    }

    public function toMail(object $notifiable): QuoteExpiredMail
    {
        return new QuoteExpiredMail($this->quote, 'key_account');
    }

    private function getActionUrl(): ?string
    {
        try {
            return RequestResource::getUrl('view', ['record' => $this->quote->request_id]);
        } catch (\Throwable) {
            return null;
        }
    }
}
