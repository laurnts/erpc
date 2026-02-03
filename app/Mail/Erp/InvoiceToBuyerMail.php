<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\BuyerInvoice;
use App\Models\EmailTemplate;
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
        
        // Use buyer_order template (since invoices come from orders)
        // Get template using new system (template ID) with fallback to old system
        $template = null;
        if (isset($settings->email_template_buyer_order_id) && $settings->email_template_buyer_order_id) {
            $template = $emailService->getTemplateForSending(
                $settings->email_template_buyer_order_id,
                EmailTemplate::TYPE_BUYER_ORDER,
                $this->invoice->team
            );
        }

        $fromAddress = $template 
            ? $emailService->getSenderEmailFromTemplate($template, $settings)
            : $emailService->getSenderEmail($settings->email_template_buyer_order ?? null, $settings);
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
        
        // Use buyer_order template (since invoices come from orders)
        // Get template using new system (template ID) with fallback to default template
        $templateId = $settings->email_template_buyer_order_id ?? null;
        $template = null;
        
        if ($templateId) {
            // Use selected template
            $template = $emailService->getTemplateForSending(
                $templateId,
                EmailTemplate::TYPE_BUYER_ORDER,
                $this->invoice->team
            );
        } else {
            // If no template ID is set, use default template
            $template = $emailService->getDefaultTemplate(EmailTemplate::TYPE_BUYER_ORDER);
        }

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

        // Use template content if available, otherwise fallback to old system for backward compatibility
        $content = '';
        $isFullHtml = false;
        
        if ($template) {
            $result = $emailService->renderTemplateContent($template, $variables);
            $content = $result['content'];
            $isFullHtml = $result['is_full_html'];
        }
        
        // Fallback to old system only if template content is empty
        if (empty($content) && $settings->email_template_buyer_order) {
            $content = $emailService->renderTemplate($settings->email_template_buyer_order, $variables);
        }

        // If template is full HTML, render it as Blade template with all necessary variables
        if ($isFullHtml && !empty($content)) {
            try {
                $renderedContent = \Illuminate\Support\Facades\Blade::render($content, [
                    'invoice' => $this->invoice,
                    'team' => $this->invoice->team,
                    'buyer' => $buyer,
                    'buyerOrder' => $this->invoice->buyerOrder,
                    'currency' => $currency,
                    'totalAmount' => $totalAmount,
                ]);
                
                return new Content(
                    htmlString: $renderedContent,
                );
            } catch (\Exception $e) {
                \Log::error('Failed to render Blade template from database', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
                // Fall back to default blade template on error
            }
        }

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
