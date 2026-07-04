<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\PortalInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class SupplierPortalUserInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PortalInvitation $invitation,
        public readonly string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Supplier Portal Access Invitation',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.supplier-portal-user-invitation',
            with: [
                'invitation' => $this->invitation,
                'acceptUrl' => $this->acceptUrl,
                'companyName' => $this->invitation->company->name,
            ],
        );
    }
}
