<?php

declare(strict_types=1);

namespace App\Actions\CustomerPortal;

use App\Enums\PortalRegistrationStatus;
use App\Mail\PortalRegistrationReceivedMail;
use App\Models\PortalRegistrationRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Records a buyer self-registration application (design D4). Creates only the
 * pending application row — never a User, Company, or portal membership.
 */
final readonly class SubmitPortalRegistration
{
    public function execute(
        int $teamId,
        string $name,
        string $email,
        string $companyName,
        ?string $phone,
        ?string $message,
        string $password,
    ): PortalRegistrationRequest {
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email address already exists. Please sign in instead.'],
            ]);
        }

        $hasPendingApplication = PortalRegistrationRequest::query()
            ->where('email', $email)
            ->where('status', PortalRegistrationStatus::Pending)
            ->exists();

        if ($hasPendingApplication) {
            throw ValidationException::withMessages([
                'email' => ['An application for this email address is already awaiting approval.'],
            ]);
        }

        $application = PortalRegistrationRequest::query()->create([
            'team_id' => $teamId,
            'name' => $name,
            'email' => $email,
            'company_name' => $companyName,
            'phone' => $phone,
            'message' => $message,
            'password' => Hash::make($password),
            'status' => PortalRegistrationStatus::Pending,
        ]);

        Mail::to($email)->send(new PortalRegistrationReceivedMail($application));

        return $application;
    }
}
