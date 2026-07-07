<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt as BaseEmailVerificationPrompt;
use Filament\Facades\Filament;

/**
 * Filament's default prompt only sends the verification email when the user
 * clicks "Resend it", while the page copy claims one was already sent. Send
 * once per session when the user first lands here after sign-in.
 */
final class EmailVerificationPrompt extends BaseEmailVerificationPrompt
{
    public function mount(): void
    {
        if ((! Filament::auth()->check()) || $this->getVerifiable()->hasVerifiedEmail()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $user = $this->getVerifiable();
        $sessionKey = 'auth.verification_email_sent.'.$user->getKey();

        if (! session()->get($sessionKey)) {
            $this->sendEmailVerificationNotification($user);
            session()->put($sessionKey, true);
        }
    }
}
