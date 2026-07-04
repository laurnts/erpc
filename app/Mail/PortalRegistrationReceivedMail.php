<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\PortalRegistrationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PortalRegistrationReceivedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PortalRegistrationRequest $application,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We received your registration application',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.portal-registration-received',
            with: [
                'application' => $this->application,
            ],
        );
    }
}
