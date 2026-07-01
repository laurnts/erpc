<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\BuyerQuote;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent when a buyer quote has expired.
 * Sent to the buyer and to key accounts assigned to that buyer.
 */
final class QuoteExpiredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @var 'buyer'|'key_account' */
    public readonly string $recipientType;

    public function __construct(
        public readonly BuyerQuote $quote,
        string $recipientType = 'buyer'
    ) {
        $this->recipientType = $recipientType === 'key_account' ? 'key_account' : 'buyer';
    }

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->quote->team->getErpSettings();
        $fromAddress = $emailService->getSenderEmail(null, $settings);

        return new Envelope(
            subject: 'Quote '.$this->quote->quote_number.' has expired',
            from: $fromAddress,
        );
    }

    public function content(): Content
    {
        $validUntil = $this->quote->valid_until?->format('d M Y') ?? '—';
        $buyerName = $this->quote->buyer->name ?? 'Buyer';

        return new Content(
            view: 'emails.quote-expired',
            with: [
                'quote' => $this->quote,
                'validUntil' => $validUntil,
                'buyerName' => $buyerName,
                'recipientType' => $this->recipientType,
                'team' => $this->quote->team,
            ],
        );
    }
}
