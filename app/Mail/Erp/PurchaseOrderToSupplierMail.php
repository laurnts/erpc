<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\EmailTemplate;
use App\Models\SupplierOrder;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PurchaseOrderToSupplierMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SupplierOrder $order
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->order->team->getErpSettings();

        // Get template using new system (template ID) with fallback to old system
        $template = null;
        if (isset($settings->email_template_supplier_order_id) && $settings->email_template_supplier_order_id) {
            $template = $emailService->getTemplateForSending(
                $settings->email_template_supplier_order_id,
                EmailTemplate::TYPE_SUPPLIER_ORDER,
                $this->order->team
            );
        }

        $fromAddress = $template
            ? $emailService->getSenderEmailFromTemplate($template, $settings)
            : $emailService->getSenderEmail($settings->email_template_supplier_order ?? null, $settings);
        $fromName = $emailService->getSenderName($settings);

        return new Envelope(
            subject: 'Purchase Order '.$this->order->po_number,
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
        $templateId = $settings->email_template_supplier_order_id ?? null;
        $template = null;

        if ($templateId) {
            // Use selected template
            $template = $emailService->getTemplateForSending(
                $templateId,
                EmailTemplate::TYPE_SUPPLIER_ORDER,
                $this->order->team
            );
        } else {
            // If no template ID is set, use default template
            $template = $emailService->getDefaultTemplate(EmailTemplate::TYPE_SUPPLIER_ORDER);
        }

        $variables = [
            'supplier_name' => $this->order->supplier->name ?? 'Supplier',
            'order_number' => $this->order->po_number,
            'order_date' => $this->order->created_at?->format('M j, Y') ?? '',
            'delivery_date' => $this->order->expected_delivery_date?->format('M j, Y') ?? 'TBD',
            'total_amount' => $this->order->formatted_total,
            'request_number' => $this->order->request->request_number ?? '',
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
        if (empty($content) && $settings->email_template_supplier_order) {
            $content = $emailService->renderTemplate($settings->email_template_supplier_order, $variables);
        }

        // If template is full HTML, render it as Blade template with all necessary variables
        if ($isFullHtml && ! empty($content)) {
            try {
                $renderedContent = \Illuminate\Support\Facades\Blade::render($content, [
                    'order' => $this->order,
                    'team' => $this->order->team,
                    'supplier' => $this->order->supplier,
                    'request' => $this->order->request,
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
            view: 'emails.purchase-order-to-supplier',
            with: [
                'order' => $this->order,
                'content' => $content,
                'team' => $this->order->team,
            ],
        );
    }
}
