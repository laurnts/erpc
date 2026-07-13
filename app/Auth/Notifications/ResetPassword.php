<?php

declare(strict_types=1);

namespace App\Auth\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseNotification;

/**
 * Panel password-reset email. Filament's default notification is queued; auth
 * mail must send immediately so it does not depend on a running queue worker.
 */
final class ResetPassword extends BaseNotification
{
    public string $url;

    protected function resetUrl(mixed $notifiable): string
    {
        return $this->url;
    }
}
