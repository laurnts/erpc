<?php

declare(strict_types=1);

namespace App\Actions\BuyerPortal;

use App\Enums\CreationSource;
use App\Enums\PortalRegistrationStatus;
use App\Enums\PortalType;
use App\Mail\PortalRegistrationApprovedMail;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\PortalRegistrationRequest;
use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail as FilamentVerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Approves a pending buyer registration: creates the buyer Company, the User
 * (reusing the stored password hash — the "hashed" cast stores an already
 * hashed value verbatim), and an active buyer portal membership for the
 * application's team, then notifies the applicant. The user's email is NOT
 * verified on first sign-in via the buyer panel's email-verification flow
 * (the application email address was never verified).
 */
final readonly class ApprovePortalRegistration
{
    public function execute(PortalRegistrationRequest $application, User $approver): void
    {
        if (! $application->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['Only pending applications can be approved.'],
            ]);
        }

        if (User::query()->where('email', $application->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['A user with this email address already exists; the application cannot be approved.'],
            ]);
        }

        $user = DB::transaction(function () use ($application, $approver): User {
            $company = Company::query()->create([
                'team_id' => $application->team_id,
                'creator_id' => $approver->getKey(),
                'name' => $application->company_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'is_buyer' => true,
                'creation_source' => CreationSource::PORTAL,
            ]);

            $user = User::query()->create([
                'name' => $application->name,
                'email' => $application->email,
                'password' => $application->password,
            ]);

            CompanyPortalUser::query()->create([
                'team_id' => $application->team_id,
                'company_id' => $company->getKey(),
                'user_id' => $user->getKey(),
                'portal' => PortalType::Buyer,
                'invited_by' => $approver->getKey(),
                'is_active' => true,
            ]);

            $application->forceFill([
                'status' => PortalRegistrationStatus::Approved,
                'decided_by' => $approver->getKey(),
                'decided_at' => now(),
            ])->save();

            return $user;
        });

        Filament::setCurrentPanel('buyer');

        $verificationNotification = app(FilamentVerifyEmail::class);
        $verificationNotification->url = Filament::getVerifyEmailUrl($user);
        $user->notify($verificationNotification);

        $signInUrl = url()->getBuyerPortalUrl('login');

        Mail::to($application->email)->send(new PortalRegistrationApprovedMail($application, $signInUrl));
    }
}
