<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupplierQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Internal-team notification when a supplier declines a sent request from the
 * supplier portal.
 */
final class SupplierQuoteDeclinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SupplierQuote $quote,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Supplier Declined to Quote: '.$this->quote->quote_number)
            ->line('A supplier declined a quote request through the supplier portal.')
            ->line('Quote: '.$this->quote->quote_number)
            ->line('Supplier: '.($this->quote->supplier->name))
            ->action('View Request', url()->getAppUrl('requests/'.$this->quote->request_id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Supplier declined to quote',
            'body' => sprintf(
                '%s — %s',
                $this->quote->quote_number,
                $this->quote->supplier->name,
            ),
            'supplier_quote_id' => $this->quote->getKey(),
            'request_id' => $this->quote->request_id,
        ];
    }
}
