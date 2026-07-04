<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupplierQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Supplier-facing won/lost notification, fired ONLY from AnnounceRfqOutcomes —
 * never from raw status transitions, so internal selection churn and the
 * staff "obtained" data-entry shortcut can neither leak nor spam. The message
 * discloses the recipient's own result only: no winner identity, no winning
 * prices, no other suppliers' existence.
 */
final class SupplierQuoteOutcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SupplierQuote $quote,
        public readonly bool $won,
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
        $mail = (new MailMessage)
            ->subject('Quote Outcome: '.$this->quote->quote_number);

        if ($this->won) {
            $mail->line('Good news — your quotation has been selected.')
                ->line('Quote: '.$this->quote->quote_number)
                ->line('For split awards, the awarded items are marked individually in the portal.');
        } else {
            $mail->line('Thank you for your quotation. After evaluation, your quote was not selected this time.')
                ->line('Quote: '.$this->quote->quote_number);
        }

        return $mail->action('View Quote Request', url()->getSupplierPortalUrl('rfqs/'.$this->quote->getKey()));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->won ? 'Your quote was selected' : 'Your quote was not selected',
            'body' => $this->quote->quote_number,
            'supplier_quote_id' => $this->quote->getKey(),
            'won' => $this->won,
        ];
    }
}
