<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\EmailTemplate;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ShipmentToBuyerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Shipment $shipment
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->shipment->team->getErpSettings();

        // Get template using new system (template ID) with fallback to old system
        $template = null;
        if (isset($settings->email_template_delivery_order_id) && $settings->email_template_delivery_order_id) {
            $template = $emailService->getTemplateForSending(
                $settings->email_template_delivery_order_id,
                EmailTemplate::TYPE_DELIVERY_ORDER,
                $this->shipment->team
            );
        }

        $fromAddress = $template
            ? $emailService->getSenderEmailFromTemplate($template, $settings)
            : $emailService->getSenderEmail($settings->email_template_delivery_order ?? null, $settings);
        $fromName = $emailService->getSenderName($settings);

        // Log sender configuration for debugging
        \Log::info('ShipmentToBuyerMail envelope configuration', [
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'template_id' => $template?->id,
            'smtp_host' => $settings->smtp_host,
            'smtp_username' => $settings->smtp_username,
        ]);

        // Build envelope with subject and from address
        $envelope = new Envelope(
            from: $fromAddress,
            subject: 'Delivery Order - '.$this->shipment->do_number ?? $this->shipment->shipment_number,
        );

        // If using Gmail SMTP and sender email doesn't match SMTP username,
        // Gmail will only accept the From address if it's verified in Gmail Settings → Send mail as
        // If not verified, Gmail will force From to match SMTP account, so we set Reply-To as fallback
        if (! empty($settings->smtp_host)
            && str_contains(strtolower((string) $settings->smtp_host), 'gmail')
            && ! empty($settings->smtp_username)
            && ! empty($fromAddress)
            && strtolower($fromAddress) !== strtolower((string) $settings->smtp_username)) {
            // Set Reply-To to ensure replies go to the desired address
            // If From address is verified in Gmail, it will work; otherwise Reply-To provides fallback
            $envelope->replyTo($fromAddress, $fromName);
            \Log::info('Gmail SMTP with different sender address: From will work if verified in Gmail "Send mail as", Reply-To set as fallback', [
                'smtp_username' => $settings->smtp_username,
                'from_address' => $fromAddress,
                'note' => 'Verify address in Gmail Settings → Accounts → Send mail as for From to work correctly',
            ]);
        }

        return $envelope;
    }

    public function content(): Content
    {
        // Ensure DO number is generated
        if ($this->shipment->do_number === null || $this->shipment->do_number === '') {
            $this->shipment->generateDoNumber();
        }

        // Load all necessary relationships (same as PDF generation)
        if (! $this->shipment->relationLoaded('items')) {
            $this->shipment->load([
                'supplierOrder.supplier',
                'supplierOrder.request.buyer',
                'items.supplierOrderItem.article',
                'request.buyer',
                'team',
            ]);
        }

        // Prepare items data with brand/model from article (same as PDF)
        $items = $this->shipment->items->map(function (ShipmentItem $shipmentItem): array {
            $supplierOrderItem = $shipmentItem->supplierOrderItem;
            $article = $supplierOrderItem?->article;

            $brand = null;
            $model = null;
            if ($article !== null && is_array($article->attributes)) {
                $brand = $article->attributes['brand'] ?? null;
                $model = $article->attributes['model'] ?? null;
            }

            return [
                'number' => $shipmentItem->sort_order + 1,
                'item_name' => $supplierOrderItem?->description ?? 'Unknown item',
                'brand' => $brand,
                'model' => $model,
                'qty' => (float) $shipmentItem->quantity_shipped,
                'remarks' => $shipmentItem->condition_notes ?? $supplierOrderItem?->notes ?? null,
            ];
        });

        $emailService = app(EmailTemplateService::class);
        $settings = $this->shipment->team->getErpSettings();

        // Get template using new system (template ID) with fallback to default template
        $templateId = $settings->email_template_delivery_order_id ?? null;
        $template = null;

        if ($templateId) {
            // Use selected template
            $template = $emailService->getTemplateForSending(
                $templateId,
                EmailTemplate::TYPE_DELIVERY_ORDER,
                $this->shipment->team
            );
        } else {
            // If no template ID is set, use default template
            $template = $emailService->getDefaultTemplate(EmailTemplate::TYPE_DELIVERY_ORDER);
        }

        // Get company details (same as PDF)
        $company = [
            'name' => $settings->company_name,
            'address' => $settings->company_address,
            'phone' => $settings->company_phone,
            'email' => $settings->company_email,
        ];

        // For inbound shipments, buyer is accessed via request->buyer
        $buyer = $this->shipment->request?->buyer ?? null;

        $variables = [
            'buyer_name' => $buyer->name ?? 'Buyer',
            'shipment_number' => $this->shipment->shipment_number,
            'do_number' => $this->shipment->do_number ?? $this->shipment->shipment_number,
            'shipment_date' => $this->shipment->shipped_at?->format('M j, Y') ?? $this->shipment->created_at?->format('M j, Y') ?? '',
            'delivery_date' => $this->shipment->expected_delivery_at?->format('M j, Y') ?? '',
            'tracking_number' => $this->shipment->tracking_number ?? 'N/A',
            'delivery_address' => $buyer->address ?? 'N/A',
            'po_number' => $this->shipment->supplierOrder?->po_number ?? 'N/A',
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
        if (empty($content) && $settings->email_template_delivery_order) {
            $content = $emailService->renderTemplate($settings->email_template_delivery_order, $variables);
        }

        // If template is full HTML, render it as Blade template with all necessary variables
        if ($isFullHtml && ! empty($content)) {
            try {
                $renderedContent = \Illuminate\Support\Facades\Blade::render($content, [
                    'shipment' => $this->shipment,
                    'items' => $items,
                    'company' => $company,
                    'team' => $this->shipment->team,
                    'buyer' => $buyer,
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
            view: 'emails.shipment-to-buyer',
            with: [
                'shipment' => $this->shipment,
                'items' => $items,
                'company' => $company,
                'content' => $content,
                'team' => $this->shipment->team,
            ],
        );
    }
}
