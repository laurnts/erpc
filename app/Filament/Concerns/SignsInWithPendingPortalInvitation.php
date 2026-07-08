<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Enums\PortalType;
use App\Models\PortalInvitation;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

/**
 * Breaks the acceptance deadlock for invited emails that already have an
 * account: panel login normally requires canAccessPanel(), which only becomes
 * true after the invitation is accepted — but accepting requires signing in.
 * When the standard attempt is rejected, this retries the same credentials
 * without the panel-access condition, and only if a pending unexpired
 * invitation for this portal exists, then sends the user straight to the
 * acceptance page. Panel content stays closed until acceptance:
 * AuthenticatePanelUser force-logs-out on every route except the acceptance
 * page itself.
 *
 * Runs strictly inside the parent's failure path. Rate limiting cannot be
 * bypassed (the parent catches its own TooManyRequestsException and returns
 * null), and only the parent's generic credential failure — keyed data.email
 * by throwFailureValidationException() — reaches the fallback: a failed
 * multi-factor challenge throws under its own form's error keys and is
 * rethrown untouched, so a wrong second factor can never be converted into
 * a password-only sign-in.
 *
 * Audit note: the parent fires a Failed auth event before throwing, so a
 * successful fallback sign-in leaves a Failed+Login event pair — that pair
 * is the signature of an invitation-fallback login, not a compromised
 * account.
 */
trait SignsInWithPendingPortalInvitation
{
    abstract protected function pendingInvitationPortal(): PortalType;

    abstract protected function pendingInvitationAcceptUrl(PortalInvitation $invitation): string;

    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (ValidationException $exception) {
            if (! array_key_exists('data.email', $exception->errors())) {
                throw $exception;
            }

            if ($this->signInForPendingInvitation()) {
                return null;
            }

            throw $exception;
        }
    }

    private function signInForPendingInvitation(): bool
    {
        /** @var array<string, mixed> $data */
        $data = $this->form->getRawState();

        $email = is_string($data['email'] ?? null) ? $data['email'] : '';
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';

        if ($email === '' || $password === '') {
            return false;
        }

        $invitation = PortalInvitation::query()
            ->where('email', $email)
            ->where('portal', $this->pendingInvitationPortal())
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();

        if ($invitation === null) {
            return false;
        }

        if (! Filament::auth()->attempt(
            ['email' => $email, 'password' => $password],
            (bool) ($data['remember'] ?? false),
        )) {
            return false;
        }

        session()->regenerate();

        $this->redirect($this->pendingInvitationAcceptUrl($invitation));

        return true;
    }
}
