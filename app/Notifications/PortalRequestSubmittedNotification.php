<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\RequestSubmissionMethod;
use App\Models\Request;
use App\Services\CustomerPortal\CustomerRequestStagePresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PortalRequestSubmittedNotification extends Notification implements ShouldQueue
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
        $isDocument = $this->request->submission_method === RequestSubmissionMethod::DOCUMENT;

        $message = (new MailMessage)
            ->subject('Permintaan Baru dari Portal: '.$this->request->request_number)
            ->line('Pelanggan mengajukan permintaan baru melalui portal pelanggan.');

        if ($isDocument) {
            $message->line('Metode: Upload dokumen — perlu direview dan diinput item oleh tim internal.');
        } else {
            $message->line('Metode: Input manual.');
        }

        return $message
            ->line('Nomor: '.$this->request->request_number)
            ->line('Judul: '.$this->request->title)
            ->line('Buyer: '.$this->request->buyer?->name)
            ->action('Lihat Permintaan', url()->getAppUrl('requests/'.$this->request->getKey()));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $suffix = $this->request->submission_method === RequestSubmissionMethod::DOCUMENT
            ? ' [Dokumen — perlu review]'
            : '';

        return [
            'title' => 'Permintaan baru dari portal'.$suffix,
            'body' => sprintf(
                '%s — %s (%s)',
                $this->request->request_number,
                $this->request->title,
                $this->request->buyer?->name ?? 'Buyer',
            ),
            'request_id' => $this->request->getKey(),
        ];
    }
}
