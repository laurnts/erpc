<?php

declare(strict_types=1);

namespace App\Livewire\Catalog;

use App\Actions\BuyerPortal\SubmitPortalRegistration;
use App\Services\Catalog\CatalogTeamResolver;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Public buyer self-registration form (design D4). Creates only a pending
 * application; staff approval later creates the buyer company, user account,
 * and portal membership.
 */
#[Layout('components.layouts.catalog')]
final class RegistrationPage extends Component
{
    use WithRateLimiting;

    public string $name = '';

    public string $email = '';

    public string $company_name = '';

    public string $phone = '';

    public string $message = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $submitted = false;

    public function submit(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->addError('email', sprintf('Too many attempts. Please try again in %d seconds.', $exception->secondsUntilAvailable));

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],
            'password' => ['required', 'string', 'confirmed', Password::default()],
        ]);

        $teamId = app(CatalogTeamResolver::class)->teamId();

        if ($teamId === null) {
            $this->addError('email', 'Registration is currently unavailable.');

            return;
        }

        app(SubmitPortalRegistration::class)->execute(
            teamId: $teamId,
            name: $this->name,
            email: $this->email,
            companyName: $this->company_name,
            phone: $this->phone !== '' ? $this->phone : null,
            message: $this->message !== '' ? $this->message : null,
            password: $this->password,
        );

        $this->reset('password', 'password_confirmation');
        $this->submitted = true;
    }

    public function render(): View
    {
        return view('livewire.catalog.registration-page')
            ->title('Register — '.config('app.name'));
    }
}
