<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\BuyerInvoice;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class InvoiceToBuyerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BuyerInvoice $invoice
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->invoice->team->getErpSettings();
        
        // Use buyer_order template if invoice template doesn't exist (since invoices come from orders)
        $templateConfig = $settings->email_template_buyer_order ?? null;

        $fromAddress = $emailService->getSenderEmail($templateConfig, $settings);
        $fromName = $emailService->getSenderName($settings);

        return new Envelope(
            subject: 'Invoice '.$this->invoice->invoice_number,
            from: $fromAddress,
        );
    }

    public function content(): Content
    {
        // Ensure items are loaded for the email template
        if (! $this->invoice->relationLoaded('items')) {
            $this->invoice->load('items');
        }

        $emailService = app(EmailTemplateService::class);
        $settings = $this->invoice->team->getErpSettings();
        
        // Use buyer_order template if invoice template doesn't exist (since invoices come from orders)
        $templateConfig = $settings->email_template_buyer_order ?? null;

        $buyer = $this->invoice->buyerOrder?->buyer ?? null;
        $currency = $this->invoice->currency ?? null;
        $totalAmount = $currency
            ? $currency->format((float) $this->invoice->total)
            : number_format((float) $this->invoice->total, 2);

        $variables = [
            'buyer_name' => $buyer->name ?? 'Buyer',
            'invoice_number' => $this->invoice->invoice_number,
            'order_number' => $this->invoice->buyerOrder?->order_number ?? '',
            'invoice_date' => $this->invoice->issued_at?->format('M j, Y') ?? now()->format('M j, Y'),
            'due_date' => $this->invoice->due_at?->format('M j, Y') ?? '',
            'total_amount' => $totalAmount,
        ];

        $content = $templateConfig ? $emailService->renderTemplate($templateConfig, $variables) : '';

        return new Content(
            view: 'emails.invoice-to-buyer',
            with: [
                'invoice' => $this->invoice,
                'content' => $content,
                'team' => $this->invoice->team,
            ],
        );
    }
}
