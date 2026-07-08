<?php

declare(strict_types=1);

namespace App\Actions\BuyerPortal;

use App\Enums\PortalRegistrationStatus;
use App\Mail\PortalRegistrationRejectedMail;
use App\Models\PortalRegistrationRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Rejects a pending buyer registration: a status flip plus a notification
 * mail. No records are created.
 */
final readonly class RejectPortalRegistration
{
    public function execute(PortalRegistrationRequest $application, User $decidedBy): void
    {
        if (! $application->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['Only pending applications can be rejected.'],
            ]);
        }

        $application->forceFill([
            'status' => PortalRegistrationStatus::Rejected,
            'decided_by' => $decidedBy->getKey(),
            'decided_at' => now(),
        ])->save();

        Mail::to($application->email)->send(new PortalRegistrationRejectedMail($application));
    }
}
