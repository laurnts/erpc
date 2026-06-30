<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Request;
use App\Services\CustomerPortal\CustomerRequestStagePresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PortalRequestStageChangedNotification extends Notification implements ShouldQueue
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
        $presenter = app(CustomerRequestStagePresenter::class);
        $statusLabel = $presenter->label($this->request);

        return (new MailMessage)
            ->subject('Request Update: '.$this->request->request_number)
            ->line('Your request status has been updated.')
            ->line('Request No.: '.$this->request->request_number)
            ->line('Title: '.$this->request->title)
            ->line('Status: '.$statusLabel)
            ->action('View Request', $this->portalRequestUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $presenter = app(CustomerRequestStagePresenter::class);

        return [
            'title' => 'Request status updated',
            'body' => sprintf(
                '%s — %s',
                $this->request->request_number,
                $presenter->label($this->request),
            ),
            'request_id' => $this->request->getKey(),
        ];
    }

    private function portalRequestUrl(): string
    {
        return url()->getCustomerPortalUrl('requests/'.$this->request->getKey());
    }
}
