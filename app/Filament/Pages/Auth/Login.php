<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Filament\Resources\CompanyResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Enums\Size;

final class Login extends \Filament\Auth\Pages\Login
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $user = Filament::auth()->user();

            if ($user?->canAccessPanel(Filament::getPanel('app'))) {
                if ($user->currentTeam !== null) {
                    $this->redirect(CompanyResource::getUrl('index', ['tenant' => $user->currentTeam]));

                    return;
                }

                $this->redirect(Filament::getPanel('app')->getUrl());

                return;
            }

            Filament::auth()->logout();
        }

        $this->form->fill();
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->size(Size::Medium)
            ->label(__('filament-panels::auth/pages/login.form.actions.authenticate.label'))
            ->submit('authenticate');
    }
}
