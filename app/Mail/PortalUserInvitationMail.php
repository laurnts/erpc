<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\PortalType;
use App\Models\PortalInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PortalUserInvitationMail extends Mailable
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
            subject: $this->portalName().' Portal Access Invitation',
        );
    }

    public function content(): Content
    {
        $recipientHasAccount = User::query()->where('email', $this->invitation->email)->exists();

        return new Content(
            markdown: 'emails.portal-user-invitation',
            with: [
                'invitation' => $this->invitation,
                'acceptUrl' => $this->acceptUrl,
                'companyName' => $this->invitation->company->name,
                'portalName' => $this->portalName(),
                'portalPitch' => $this->portalPitch($recipientHasAccount),
                'buttonLabel' => $recipientHasAccount ? 'Sign in to accept' : 'Accept Invitation',
            ],
        );
    }

    private function portalName(): string
    {
        return $this->invitation->portal === PortalType::Supplier ? 'Supplier' : 'Buyer';
    }

    private function portalPitch(bool $recipientHasAccount): string
    {
        if ($recipientHasAccount) {
            return 'You already have an account. Sign in to accept access to '.$this->invitation->company->name.'.';
        }

        return $this->invitation->portal === PortalType::Supplier
            ? 'Click the button below to create your account, maintain your article prices and availability, and respond to quote requests.'
            : 'Click the button below to create your account and start submitting goods and services requests on your own.';
    }
}
