<?php

declare(strict_types=1);

namespace App\Auth\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseNotification;

/**
 * Panel email-verification message. Filament's default notification is queued;
 * auth mail must send immediately so it does not depend on a running queue worker.
 */
final class VerifyEmail extends BaseNotification
{
    public string $url;

    protected function verificationUrl($notifiable): string
    {
        return $this->url;
    }
}
