<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PortalShipmentStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Shipment $shipment,
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
        return (new MailMessage)
            ->subject('Shipment Update: '.$this->shipment->shipment_number)
            ->line('The shipment status for your request has been updated.')
            ->line('Shipment No.: '.$this->shipment->shipment_number)
            ->line('Status: '.$this->shipment->status->getLabel())
            ->when(
                filled($this->shipment->tracking_number),
                fn (MailMessage $mail) => $mail->line('Tracking No.: '.$this->shipment->tracking_number),
            )
            ->action('View Request', $this->portalRequestUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Shipment status updated',
            'body' => sprintf(
                '%s — %s',
                $this->shipment->shipment_number,
                $this->shipment->status->getLabel(),
            ),
            'request_id' => $this->shipment->request_id,
            'shipment_id' => $this->shipment->getKey(),
        ];
    }

    private function portalRequestUrl(): string
    {
        return url()->getBuyerPortalUrl('requests/'.$this->shipment->request_id);
    }
}
