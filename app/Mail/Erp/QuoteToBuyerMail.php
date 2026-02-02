<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\BuyerQuote;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class QuoteToBuyerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BuyerQuote $quote
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->quote->team->getErpSettings();
        $templateConfig = $settings->email_template_buyer_quote;

        $fromAddress = $emailService->getSenderEmail($templateConfig, $settings);
        $fromName = $emailService->getSenderName($settings);

        return new Envelope(
            subject: 'Quote '.$this->quote->quote_number,
            from: $fromAddress,
        );
    }

    public function content(): Content
    {
        // Ensure items are loaded for the email template
        if (! $this->quote->relationLoaded('items')) {
            $this->quote->load('items');
        }

        $emailService = app(EmailTemplateService::class);
        $settings = $this->quote->team->getErpSettings();
        $templateConfig = $settings->email_template_buyer_quote;

        $currency = $this->quote->currency ?? null;
        $totalAmount = $currency
            ? $currency->format((float) $this->quote->total)
            : number_format((float) $this->quote->total, 2);

        $variables = [
            'buyer_name' => $this->quote->buyer->name ?? 'Buyer',
            'quote_number' => $this->quote->quote_number,
            'request_number' => $this->quote->request->request_number ?? '',
            'valid_until' => $this->quote->valid_until?->format('M j, Y') ?? '',
            'total_amount' => $totalAmount,
        ];

        $content = $emailService->renderTemplate($templateConfig, $variables);

        return new Content(
            view: 'emails.quote-to-buyer',
            with: [
                'quote' => $this->quote,
                'content' => $content,
                'team' => $this->quote->team,
            ],
        );
    }
}
