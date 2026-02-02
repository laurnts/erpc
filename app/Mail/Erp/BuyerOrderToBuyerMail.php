<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Models\BuyerOrder;
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
        $templateConfig = $settings->email_template_buyer_order;

        $fromAddress = $emailService->getSenderEmail($templateConfig, $settings);
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
        $templateConfig = $settings->email_template_buyer_order;

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

        $content = $emailService->renderTemplate($templateConfig, $variables);

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
