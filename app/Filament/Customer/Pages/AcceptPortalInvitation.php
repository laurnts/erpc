<?php

declare(strict_types=1);

namespace App\Filament\Customer\Pages;

use App\Models\CompanyPortalUser;
use App\Models\PortalInvitation;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Http\Middleware\Authenticate;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class AcceptPortalInvitation extends Page implements HasForms
{
    use CanUseDatabaseTransactions;
    use InteractsWithForms;

    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static ?string $slug = 'invitation/{token}';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var string|array<string>
     */
    protected static string|array $withoutRouteMiddleware = [
        Authenticate::class,
    ];

    protected string $view = 'filament.customer.pages.accept-portal-invitation';

    public static function canAccess(): bool
    {
        return true;
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
        return 'Create Customer Portal Account';
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

        $this->wrapInDatabaseTransaction(function () use ($data): void {
            $user = User::query()->where('email', $this->invitation->email)->first();

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $this->invitation->email,
                    'password' => Hash::make($data['password']),
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->forceFill([
                    'name' => $data['name'],
                    'password' => Hash::make($data['password']),
                ])->save();
            }

            CompanyPortalUser::query()->updateOrCreate(
                [
                    'company_id' => $this->invitation->company_id,
                    'user_id' => $user->getKey(),
                ],
                [
                    'team_id' => $this->invitation->team_id,
                    'invited_by' => $this->invitation->invited_by,
                    'is_active' => true,
                ],
            );

            $this->invitation->markAccepted();
        });

        Notification::make()
            ->title('Account created successfully')
            ->body('Please sign in to the customer portal.')
            ->success()
            ->send();

        $this->redirect(filament()->getPanel('customer')->getLoginUrl());
    }
}
