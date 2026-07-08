<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\RequestSubmissionMethod;
use App\Models\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PortalRequestReceivedConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Request $request,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Request Received: '.$this->request->request_number)
            ->line('We have received your request and our team will review it shortly.');

        if ($this->request->submission_method === RequestSubmissionMethod::DOCUMENT) {
            $message->line('Your uploaded documents are attached to the request for our team to review.');
        }

        return $message
            ->line('Request No.: '.$this->request->request_number)
            ->line('Title: '.$this->request->title)
            ->action('View Request', $this->portalRequestUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Request received',
            'body' => sprintf(
                '%s — %s',
                $this->request->request_number,
                $this->request->title,
            ),
            'request_id' => $this->request->getKey(),
        ];
    }

    private function portalRequestUrl(): string
    {
        return url()->getCustomerPortalUrl('requests/'.$this->request->getKey());
    }
}
