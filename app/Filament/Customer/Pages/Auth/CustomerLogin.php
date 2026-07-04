<?php

declare(strict_types=1);

namespace App\Filament\Customer\Pages\Auth;

use App\Filament\Customer\Pages\CustomerDashboard;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Support\Enums\Size;

final class CustomerLogin extends BaseLogin
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $user = Filament::auth()->user();

            if ($user instanceof FilamentUser && $user->canAccessPanel(Filament::getPanel('customer'))) {
                $this->redirect(CustomerDashboard::getUrl(panel: 'customer'));

                return;
            }

            Filament::auth()->logout();
        }

        $this->form->fill();
    }

    /**
     * @return array<string, string>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'fi-customer-login',
        ];
    }

    public function getTitle(): string
    {
        return 'Buyer Sign in';
    }

    public function getHeading(): string
    {
        return 'Buyer Sign in';
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->size(Size::Medium)
            ->label('Sign In')
            ->submit('authenticate');
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
        ];
    }
}
