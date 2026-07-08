<?php

declare(strict_types=1);

namespace App\Filament\Buyer\Pages\Auth;

use App\Filament\Buyer\Pages\BuyerDashboard;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Support\Enums\Size;

final class BuyerLogin extends BaseLogin
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $user = Filament::auth()->user();

            if ($user instanceof FilamentUser && $user->canAccessPanel(Filament::getPanel('buyer'))) {
                $this->redirect(BuyerDashboard::getUrl(panel: 'buyer'));

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
            'class' => 'fi-buyer-login',
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
