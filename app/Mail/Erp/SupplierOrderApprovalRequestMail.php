<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Filament\Resources\SupplierOrderApprovals\SupplierOrderApprovalResource;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class SupplierOrderApprovalRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SupplierOrder $order,
        public readonly User $approver
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->order->team->getErpSettings();

        $fromAddress = $emailService->getSenderEmail(null, $settings);
        $fromName = $emailService->getSenderName($settings);

        return new Envelope(
            subject: 'Supplier Order Approval Required: '.$this->order->po_number,
            from: $fromAddress,
        );
    }

    public function content(): Content
    {
        // Ensure relationships are loaded
        if (! $this->order->relationLoaded('supplier')) {
            $this->order->load('supplier');
        }
        if (! $this->order->relationLoaded('request')) {
            $this->order->load('request');
        }

        // Build approval URL - link to approval resource view page
        $approvalUrl = SupplierOrderApprovalResource::getUrl('view', ['record' => $this->order->id]);

        return new Content(
            view: 'emails.supplier-order-approval-request',
            with: [
                'order' => $this->order,
                'approver' => $this->approver,
                'approvalUrl' => $approvalUrl,
                'team' => $this->order->team,
            ],
        );
    }
}
