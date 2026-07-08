<?php

declare(strict_types=1);

use App\Actions\BuyerPortal\ApprovePortalRegistration;
use App\Actions\BuyerPortal\RejectPortalRegistration;
use App\Auth\Notifications\VerifyEmail;
use App\Enums\PortalRegistrationStatus;
use App\Enums\PortalType;
use App\Filament\Buyer\Pages\Auth\BuyerLogin;
use App\Filament\Pages\Auth\EmailVerificationPrompt;
use App\Livewire\Catalog\RegistrationPage;
use App\Mail\PortalRegistrationApprovedMail;
use App\Mail\PortalRegistrationReceivedMail;
use App\Mail\PortalRegistrationRejectedMail;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\PortalRegistrationRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();

    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team = $this->admin->personalTeam();

    config(['catalog.team_id' => $this->team->getKey()]);
});

describe('Application submission', function (): void {
    it('stores a pending application with a hashed password and creates nothing else', function (): void {
        $userCountBefore = User::query()->count();
        $companyCountBefore = Company::query()->count();

        livewire(RegistrationPage::class)
            ->set('name', 'Jane Applicant')
            ->set('email', 'jane@applicant.test')
            ->set('company_name', 'Applicant Trading BV')
            ->set('phone', '+31 6 1234 5678')
            ->set('message', 'We buy widgets.')
            ->set('password', 'SuperSecret123!')
            ->set('password_confirmation', 'SuperSecret123!')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true)
            ->assertSee('Application received');

        $application = PortalRegistrationRequest::query()->firstOrFail();

        expect($application->status)->toBe(PortalRegistrationStatus::Pending)
            ->and($application->team_id)->toBe($this->team->getKey())
            ->and($application->email)->toBe('jane@applicant.test')
            ->and($application->company_name)->toBe('Applicant Trading BV')
            ->and($application->password)->not->toBe('SuperSecret123!')
            ->and(Hash::check('SuperSecret123!', $application->password))->toBeTrue()
            ->and(User::query()->count())->toBe($userCountBefore)
            ->and(Company::query()->count())->toBe($companyCountBefore)
            ->and(CompanyPortalUser::query()->count())->toBe(0);

        Mail::assertSent(PortalRegistrationReceivedMail::class, fn (PortalRegistrationReceivedMail $mail): bool => $mail->hasTo('jane@applicant.test'));
    });

    it('rejects a duplicate application while one is pending', function (): void {
        PortalRegistrationRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'email' => 'jane@applicant.test',
        ]);

        livewire(RegistrationPage::class)
            ->set('name', 'Jane Applicant')
            ->set('email', 'jane@applicant.test')
            ->set('company_name', 'Applicant Trading BV')
            ->set('password', 'SuperSecret123!')
            ->set('password_confirmation', 'SuperSecret123!')
            ->call('submit')
            ->assertHasErrors('email');

        expect(PortalRegistrationRequest::query()->count())->toBe(1);
    });

    it('allows re-application after a rejection', function (): void {
        PortalRegistrationRequest::factory()->rejected()->create([
            'team_id' => $this->team->getKey(),
            'email' => 'jane@applicant.test',
        ]);

        livewire(RegistrationPage::class)
            ->set('name', 'Jane Applicant')
            ->set('email', 'jane@applicant.test')
            ->set('company_name', 'Applicant Trading BV')
            ->set('password', 'SuperSecret123!')
            ->set('password_confirmation', 'SuperSecret123!')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        expect(PortalRegistrationRequest::query()->count())->toBe(2);
    });

    it('rejects an application for an email that already has a user account', function (): void {
        User::factory()->create(['email' => 'existing@applicant.test']);

        livewire(RegistrationPage::class)
            ->set('name', 'Jane Applicant')
            ->set('email', 'existing@applicant.test')
            ->set('company_name', 'Applicant Trading BV')
            ->set('password', 'SuperSecret123!')
            ->set('password_confirmation', 'SuperSecret123!')
            ->call('submit')
            ->assertHasErrors('email');

        expect(PortalRegistrationRequest::query()->count())->toBe(0);
    });

    it('does not let a pending applicant sign in', function (): void {
        PortalRegistrationRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'email' => 'pending@applicant.test',
            'password' => Hash::make('SuperSecret123!'),
        ]);

        expect(Auth::guard('buyer')->attempt([
            'email' => 'pending@applicant.test',
            'password' => 'SuperSecret123!',
        ]))->toBeFalse();
    });
});

describe('Approval', function (): void {
    it('creates the buyer company, user, and active buyer membership, reusing the stored hash', function (): void {
        $application = PortalRegistrationRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'name' => 'Jane Applicant',
            'email' => 'jane@applicant.test',
            'company_name' => 'Applicant Trading BV',
            'password' => Hash::make('SuperSecret123!'),
        ]);

        app(ApprovePortalRegistration::class)->execute($application, $this->admin);

        $application->refresh();
        $company = Company::query()->where('name', 'Applicant Trading BV')->firstOrFail();
        $user = User::query()->where('email', 'jane@applicant.test')->firstOrFail();
        $membership = CompanyPortalUser::query()
            ->where('user_id', $user->getKey())
            ->where('company_id', $company->getKey())
            ->firstOrFail();

        expect($application->status)->toBe(PortalRegistrationStatus::Approved)
            ->and($application->decided_by)->toBe($this->admin->getKey())
            ->and($application->decided_at)->not->toBeNull()
            ->and($company->is_buyer)->toBeTrue()
            ->and($company->team_id)->toBe($this->team->getKey())
            ->and($user->password)->toBe($application->password)
            ->and(Hash::check('SuperSecret123!', $user->password))->toBeTrue()
            ->and($user->email_verified_at)->toBeNull()
            ->and($membership->portal)->toBe(PortalType::Buyer)
            ->and($membership->is_active)->toBeTrue()
            ->and($membership->team_id)->toBe($this->team->getKey());

        Mail::assertSent(PortalRegistrationApprovedMail::class, fn (PortalRegistrationApprovedMail $mail): bool => $mail->hasTo('jane@applicant.test'));

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
        );
    });

    it('lets the approved user sign in before email verification and reach the verification prompt', function (): void {
        $application = PortalRegistrationRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'email' => 'jane@applicant.test',
            'password' => Hash::make('SuperSecret123!'),
        ]);

        app(ApprovePortalRegistration::class)->execute($application, $this->admin);

        $user = User::query()->where('email', 'jane@applicant.test')->firstOrFail();

        expect($user->canAccessPanel(Filament::getPanel('buyer')))->toBeTrue()
            ->and($user->hasVerifiedEmail())->toBeFalse();

        Filament::setCurrentPanel('buyer');

        livewire(BuyerLogin::class)
            ->fillForm([
                'email' => 'jane@applicant.test',
                'password' => 'SuperSecret123!',
            ])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user, 'buyer');

        Notification::assertSentTo($user, VerifyEmail::class);
    });

    it('sends a verification email when the user first lands on the verification prompt', function (): void {
        $application = PortalRegistrationRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'email' => 'jane@applicant.test',
            'password' => Hash::make('SuperSecret123!'),
        ]);

        app(ApprovePortalRegistration::class)->execute($application, $this->admin);

        $user = User::query()->where('email', 'jane@applicant.test')->firstOrFail();

        Notification::fake();

        $this->actingAs($user, 'buyer');
        Filament::setCurrentPanel('buyer');

        livewire(EmailVerificationPrompt::class)->assertSuccessful();

        Notification::assertSentTo($user, VerifyEmail::class);
    });

    it('refuses to approve a non-pending application', function (): void {
        $application = PortalRegistrationRequest::factory()->rejected()->create([
            'team_id' => $this->team->getKey(),
        ]);

        app(ApprovePortalRegistration::class)->execute($application, $this->admin);
    })->throws(Illuminate\Validation\ValidationException::class);

    it('refuses to approve when a user with the email appeared in the meantime', function (): void {
        $application = PortalRegistrationRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'email' => 'raced@applicant.test',
        ]);

        User::factory()->create(['email' => 'raced@applicant.test']);

        expect(fn () => app(ApprovePortalRegistration::class)->execute($application, $this->admin))
            ->toThrow(Illuminate\Validation\ValidationException::class);

        expect($application->refresh()->status)->toBe(PortalRegistrationStatus::Pending)
            ->and(Company::query()->where('name', $application->company_name)->exists())->toBeFalse();
    });
});

describe('Rejection', function (): void {
    it('flips the status, records the decision, notifies the applicant, and creates nothing', function (): void {
        $application = PortalRegistrationRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'email' => 'jane@applicant.test',
        ]);

        $userCountBefore = User::query()->count();
        $companyCountBefore = Company::query()->count();

        app(RejectPortalRegistration::class)->execute($application, $this->admin);

        $application->refresh();

        expect($application->status)->toBe(PortalRegistrationStatus::Rejected)
            ->and($application->decided_by)->toBe($this->admin->getKey())
            ->and($application->decided_at)->not->toBeNull()
            ->and(User::query()->count())->toBe($userCountBefore)
            ->and(Company::query()->count())->toBe($companyCountBefore)
            ->and(CompanyPortalUser::query()->count())->toBe(0);

        Mail::assertSent(PortalRegistrationRejectedMail::class, fn (PortalRegistrationRejectedMail $mail): bool => $mail->hasTo('jane@applicant.test'));

        expect(Auth::guard('buyer')->attempt([
            'email' => 'jane@applicant.test',
            'password' => 'password',
        ]))->toBeFalse();
    });
});
