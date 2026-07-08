<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BuyerQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PortalBuyerQuoteSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly BuyerQuote $buyerQuote,
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
        $currency = $this->buyerQuote->currency?->code ?? '';
        $total = number_format((float) $this->buyerQuote->total, 2);

        return (new MailMessage)
            ->subject('New Quote: '.$this->buyerQuote->quote_number)
            ->line('A quote has been sent for your request.')
            ->line('Quote No.: '.$this->buyerQuote->quote_number.' (v'.$this->buyerQuote->version.')')
            ->line('Total: '.$currency.' '.$total)
            ->when(
                $this->buyerQuote->valid_until !== null,
                fn (MailMessage $mail) => $mail->line('Valid until: '.$this->buyerQuote->valid_until->format('d M Y')),
            )
            ->action('View & Confirm', $this->portalRequestUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'New quote awaiting confirmation',
            'body' => sprintf(
                '%s — %s',
                $this->buyerQuote->quote_number,
                $this->buyerQuote->request?->request_number ?? 'Request',
            ),
            'request_id' => $this->buyerQuote->request_id,
            'buyer_quote_id' => $this->buyerQuote->getKey(),
        ];
    }

    private function portalRequestUrl(): string
    {
        return url()->getBuyerPortalUrl('requests/'.$this->buyerQuote->request_id);
    }
}
