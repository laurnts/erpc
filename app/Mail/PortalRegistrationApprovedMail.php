<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\PortalRegistrationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PortalRegistrationApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PortalRegistrationRequest $application,
        public readonly string $signInUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your buyer portal access has been approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.portal-registration-approved',
            with: [
                'application' => $this->application,
                'signInUrl' => $this->signInUrl,
            ],
        );
    }
}
