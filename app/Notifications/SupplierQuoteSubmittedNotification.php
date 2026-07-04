<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupplierQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Internal-team notification when a supplier submits their quotation through
 * the supplier portal (mirror of PortalRequestSubmittedNotification).
 */
final class SupplierQuoteSubmittedNotification extends Notification implements ShouldQueue
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
            ->subject('Supplier Quote Submitted: '.$this->quote->quote_number)
            ->line('A supplier submitted their quotation through the supplier portal.')
            ->line('Quote: '.$this->quote->quote_number)
            ->line('Supplier: '.($this->quote->supplier->name))
            ->line('Total: '.$this->quote->formatted_total)
            ->action('View Request', url()->getAppUrl('requests/'.$this->quote->request_id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Supplier quote submitted via portal',
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
