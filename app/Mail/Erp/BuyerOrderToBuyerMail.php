<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\BuyerOrder;
use App\Models\EmailTemplate;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class BuyerOrderToBuyerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BuyerOrder $order
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->order->team->getErpSettings();
        
        // Get template using new system (template ID) with fallback to old system
        $template = null;
        if (isset($settings->email_template_buyer_order_id) && $settings->email_template_buyer_order_id) {
            $template = $emailService->getTemplateForSending(
                $settings->email_template_buyer_order_id,
                EmailTemplate::TYPE_BUYER_ORDER,
                $this->order->team
            );
        }

        $fromAddress = $template 
            ? $emailService->getSenderEmailFromTemplate($template, $settings)
            : $emailService->getSenderEmail($settings->email_template_buyer_order ?? null, $settings);
        $fromName = $emailService->getSenderName($settings);

        return new Envelope(
            subject: 'Order '.$this->order->order_number,
            from: $fromAddress,
        );
    }

    public function content(): Content
    {
        // Ensure items are loaded for the email template
        if (! $this->order->relationLoaded('items')) {
            $this->order->load('items');
        }

        $emailService = app(EmailTemplateService::class);
        $settings = $this->order->team->getErpSettings();
        
        // Get template using new system (template ID) with fallback to default template
        $templateId = $settings->email_template_buyer_order_id ?? null;
        $template = null;
        
        if ($templateId) {
            // Use selected template
            $template = $emailService->getTemplateForSending(
                $templateId,
                EmailTemplate::TYPE_BUYER_ORDER,
                $this->order->team
            );
        } else {
            // If no template ID is set, use default template
            $template = $emailService->getDefaultTemplate(EmailTemplate::TYPE_BUYER_ORDER);
        }

        $currency = $this->order->buyerQuote?->currency ?? null;
        $totalAmount = $currency
            ? $currency->format((float) $this->order->total)
            : number_format((float) $this->order->total, 2);

        $variables = [
            'buyer_name' => $this->order->buyer->name ?? 'Buyer',
            'order_number' => $this->order->order_number,
            'order_date' => $this->order->ordered_at?->format('M j, Y') ?? now()->format('M j, Y'),
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
                    'order' => $this->order,
                    'team' => $this->order->team,
                    'buyer' => $this->order->buyer,
                    'buyerQuote' => $this->order->buyerQuote,
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
            view: 'emails.buyer-order-to-buyer',
            with: [
                'order' => $this->order,
                'content' => $content,
                'team' => $this->order->team,
            ],
        );
    }
}
