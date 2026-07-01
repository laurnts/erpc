<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Filament\Resources\ProfitAndLossResource;
use App\Models\ProfitAndLoss;
use App\Models\User;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ProfitAndLossApprovalRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ProfitAndLoss $profitAndLoss,
        public readonly User $approver
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->profitAndLoss->team->getErpSettings();

        $fromAddress = $emailService->getSenderEmail(null, $settings);
        $fromName = $emailService->getSenderName($settings);

        return new Envelope(
            subject: 'Profit & Loss Approval Required: '.$this->profitAndLoss->pnl_number,
            from: $fromAddress,
        );
    }

    public function content(): Content
    {
        // Ensure relationships are loaded
        if (! $this->profitAndLoss->relationLoaded('request')) {
            $this->profitAndLoss->load('request');
        }
        if (! $this->profitAndLoss->relationLoaded('preparedBy')) {
            $this->profitAndLoss->load('preparedBy');
        }

        // Build approval URL - link to PNL view page
        $approvalUrl = ProfitAndLossResource::getUrl('view', ['record' => $this->profitAndLoss->id]);

        return new Content(
            view: 'emails.profit-and-loss-approval-request',
            with: [
                'profitAndLoss' => $this->profitAndLoss,
                'approver' => $this->approver,
                'approvalUrl' => $approvalUrl,
                'team' => $this->profitAndLoss->team,
            ],
        );
    }
}
