<?php

declare(strict_types=1);

namespace App\Mail\Erp;

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
        $templateConfig = $settings->email_template_supplier_order ?? null;

        $fromAddress = $emailService->getSenderEmail($templateConfig, $settings);
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
        $templateConfig = $settings->email_template_supplier_order ?? null;

        $variables = [
            'supplier_name' => $this->order->supplier->name ?? 'Supplier',
            'order_number' => $this->order->po_number,
            'order_date' => $this->order->created_at?->format('M j, Y') ?? '',
            'delivery_date' => $this->order->expected_delivery_date?->format('M j, Y') ?? 'TBD',
            'total_amount' => $this->order->formatted_total,
            'request_number' => $this->order->request->request_number ?? '',
        ];

        $content = $templateConfig ? $emailService->renderTemplate($templateConfig, $variables) : '';

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
