<?php

declare(strict_types=1);

namespace App\Filament\Customer\Pages;

use App\Actions\Portal\AcceptPortalInvitation as AcceptPortalInvitationAction;
use App\Enums\PortalType;
use App\Http\Middleware\AuthenticatePanelUser;
use App\Http\Middleware\InitializeCustomerPortalContext;
use App\Models\PortalInvitation;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Http\Middleware\Authenticate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rules\Password;

final class AcceptPortalInvitation extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static ?string $slug = 'invitation/{token}';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var string|array<string>
     */
    protected static string|array $withoutRouteMiddleware = [
        Authenticate::class,
        AuthenticatePanelUser::class,
        InitializeCustomerPortalContext::class,
    ];

    protected string $view = 'filament.customer.pages.accept-portal-invitation';

    public static function canAccess(): bool
    {
        return true;
    }

    public static function isEmailVerificationRequired(Panel $panel): bool
    {
        return false;
    }

    public ?string $token = null;

    public ?PortalInvitation $invitation = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->invitation = PortalInvitation::query()
            ->where('token', $token)
            ->where('portal', PortalType::Customer)
            ->whereNull('accepted_at')
            ->with('company')
            ->firstOrFail();

        $this->form->fill([
            'name' => $this->invitation->name,
            'email' => $this->invitation->email,
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Accept Portal Invitation';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Create Buyer Portal Account';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'You have been invited to access the portal for '.$this->invitation?->company?->name;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->confirmed(),
                TextInput::make('password_confirmation')
                    ->label('Confirm Password')
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function accept(): void
    {
        if ($this->invitation === null) {
            return;
        }

        $data = $this->form->getState();

        app(AcceptPortalInvitationAction::class)->execute(
            $this->invitation,
            (string) $data['name'],
            (string) $data['password'],
        );

        Notification::make()
            ->title('Account created successfully')
            ->body('Please sign in to the buyer portal.')
            ->success()
            ->send();

        $this->redirect(filament()->getPanel('customer')->getLoginUrl());
    }
}
