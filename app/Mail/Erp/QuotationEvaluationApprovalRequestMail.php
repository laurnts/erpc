<?php

declare(strict_types=1);

namespace App\Mail\Erp;

use App\Filament\Resources\QuotationEvaluationResource;
use App\Models\EmailTemplate;
use App\Models\QuotationEvaluation;
use App\Models\User;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class QuotationEvaluationApprovalRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly QuotationEvaluation $quotationEvaluation,
        public readonly User $approver
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->quotationEvaluation->team->getErpSettings();

        $fromAddress = $emailService->getSenderEmail(null, $settings);
        $fromName = $emailService->getSenderName($settings);

        return new Envelope(
            subject: 'Quotation Evaluation Approval Required: '.$this->quotationEvaluation->qe_number,
            from: $fromAddress,
        );
    }

    public function content(): Content
    {
        // Ensure relationships are loaded
        if (! $this->quotationEvaluation->relationLoaded('request')) {
            $this->quotationEvaluation->load('request');
        }
        if (! $this->quotationEvaluation->relationLoaded('preparedBy')) {
            $this->quotationEvaluation->load('preparedBy');
        }

        // Build approval URL - link to QE view page
        $approvalUrl = QuotationEvaluationResource::getUrl('view', ['record' => $this->quotationEvaluation->id]);

        return new Content(
            view: 'emails.quotation-evaluation-approval-request',
            with: [
                'quotationEvaluation' => $this->quotationEvaluation,
                'approver' => $this->approver,
                'approvalUrl' => $approvalUrl,
                'team' => $this->quotationEvaluation->team,
            ],
        );
    }
}
