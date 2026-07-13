<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Team;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class TestEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Team $team
    ) {}

    public function envelope(): Envelope
    {
        $emailService = app(EmailTemplateService::class);
        $settings = $this->team->getErpSettings();

        $fromAddress = $emailService->getSenderEmail(null, $settings);
        $emailService->getSenderName($settings);

        return new Envelope(
            from: $fromAddress,
            subject: 'Test Email from '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.test-email',
            with: [
                'team' => $this->team,
            ],
        );
    }
}
