<?php

declare(strict_types=1);

namespace App\Filament\Buyer\Pages;

use App\Actions\Portal\AcceptPortalInvitation as AcceptPortalInvitationAction;
use App\Enums\PortalType;
use App\Filament\Buyer\Resources\BuyerRequestResource;
use App\Http\Middleware\AuthenticatePanelUser;
use App\Http\Middleware\InitializeBuyerPortalContext;
use App\Models\PortalInvitation;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Http\Middleware\Authenticate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
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
        InitializeBuyerPortalContext::class,
    ];

    protected string $view = 'filament.buyer.pages.accept-portal-invitation';

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
     * Whether the invited email already has a user account. Existing accounts
     * accept by signing in (no password set here); only new emails create an
     * account through the form below.
     */
    public bool $accountExists = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->invitation = PortalInvitation::query()
            ->where('token', $token)
            ->where('portal', PortalType::Buyer)
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('company')
            ->firstOrFail();

        $this->accountExists = User::query()->where('email', $this->invitation->email)->exists();

        if (! $this->accountExists) {
            /** @phpstan-ignore property.notFound */
            $this->form->fill([
                'name' => $this->invitation->name,
                'email' => $this->invitation->email,
            ]);
        }
    }

    public function getTitle(): string
    {
        return 'Accept Portal Invitation';
    }

    public function getHeading(): string
    {
        return $this->accountExists ? 'Accept Portal Invitation' : 'Create Buyer Portal Account';
    }

    public function getSubheading(): string
    {
        return 'You have been invited to access the portal for '.$this->invitation?->company?->name;
    }

    public function form(Schema $schema): Schema
    {
        if ($this->accountExists) {
            return $schema->components([])->statePath('data');
        }

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
        if (! $this->invitation instanceof \App\Models\PortalInvitation) {
            return;
        }

        if ($this->accountExists) {
            $this->acceptAsExistingUser();

            return;
        }

        /** @phpstan-ignore property.notFound */
        $data = $this->form->getState();

        app(AcceptPortalInvitationAction::class)->acceptAsNewUser(
            $this->invitation,
            (string) $data['name'],
            (string) $data['password'],
        );

        Notification::make()
            ->title('Account created successfully')
            ->body('Please sign in to the buyer portal.')
            ->success()
            ->send();

        $this->redirect(filament()->getPanel('buyer')->getLoginUrl());
    }

    private function acceptAsExistingUser(): void
    {
        /** @var User|null $user */
        $user = auth('buyer')->user();

        if ($user === null || $user->email !== $this->invitation->email) {
            session()->put('url.intended', url()->getBuyerPortalUrl('invitation/'.$this->token));

            Notification::make()
                ->title('Please sign in to accept')
                ->body('This invitation is for '.$this->invitation->email.'. Sign in to that account to accept it.')
                ->warning()
                ->send();

            $this->redirect(filament()->getPanel('buyer')->getLoginUrl());

            return;
        }

        app(AcceptPortalInvitationAction::class)->acceptAsExistingUser($this->invitation, $user);

        Notification::make()
            ->title('Access granted')
            ->body('You now have access to '.$this->invitation->company?->name.'.')
            ->success()
            ->send();

        $this->redirect(BuyerRequestResource::getUrl('index', panel: 'buyer'));
    }
}
