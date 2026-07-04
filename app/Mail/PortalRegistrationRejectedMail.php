<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\PortalRegistrationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PortalRegistrationRejectedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PortalRegistrationRequest $application,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update on your registration application',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.portal-registration-rejected',
            with: [
                'application' => $this->application,
            ],
        );
    }
}
