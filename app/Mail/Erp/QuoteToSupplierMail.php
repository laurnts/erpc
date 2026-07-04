<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\SupplierQuote;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class QuoteToSupplierMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SupplierQuote $quote
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->quote->team->getErpSettings();
        $templateConfig = $settings->email_template_quote_to_supplier ?? null;

        $fromAddress = $emailService->getSenderEmail($templateConfig, $settings);
        $fromName = $emailService->getSenderName($settings);

        return new Envelope(
            subject: 'Quote Request - '.($this->quote->request->request_number ?? ''),
            from: $fromAddress,
        );
    }

    public function content(): Content
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->quote->team->getErpSettings();
        $templateConfig = $settings->email_template_quote_to_supplier ?? null;

        $currency = $this->quote->currency ?? null;
        $totalAmount = $currency
            ? $currency->format((float) $this->quote->total)
            : number_format((float) $this->quote->total, 2);

        $variables = [
            'supplier_name' => $this->quote->supplier->name ?? 'Supplier',
            'quote_number' => $this->quote->quote_number ?? '',
            'request_number' => $this->quote->request->request_number ?? '',
            'valid_until' => $this->quote->valid_until?->format('M j, Y') ?? '',
            'total_amount' => $totalAmount,
        ];

        $content = $emailService->renderTemplate($templateConfig, $variables);

        return new Content(
            view: 'emails.quote-to-supplier',
            with: [
                'quote' => $this->quote,
                'content' => $content,
                'team' => $this->quote->team,
                'portalUrl' => config('app.supplier_portal_enabled')
                    ? url()->getSupplierPortalUrl('rfqs')
                    : null,
            ],
        );
    }
}
