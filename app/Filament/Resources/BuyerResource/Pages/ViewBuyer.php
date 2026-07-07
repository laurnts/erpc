<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerResource\Pages;

use App\Actions\Portal\InvitePortalUser;
use App\Enums\PortalType;
use App\Filament\Resources\BuyerResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewBuyer extends ViewRecord
{
    protected static string $resource = BuyerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invitePortalUser')
                ->label('Invite Portal User')
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->visible(function (): bool {
                    if (! config('app.customer_portal_enabled', true)) {
                        return false;
                    }

                    /** @var \App\Models\Company $record */
                    $record = $this->getRecord();

                    /** @var \App\Models\Team|null $team */
                    $team = Filament::getTenant();

                    if ($team === null) {
                        return false;
                    }

                    return ! $record->hasActivePortalMembership(PortalType::Customer, $team->getKey());
                })
                ->schema([
                    TextInput::make('name')
                        ->label('Contact Name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data, \App\Models\Company $record): void {
                    /** @var \App\Models\Team $team */
                    $team = Filament::getTenant();

                    /** @var \App\Models\User $invitedBy */
                    $invitedBy = auth()->user();

                    app(InvitePortalUser::class)->execute(
                        team: $team,
                        company: $record,
                        portal: PortalType::Customer,
                        email: $data['email'],
                        name: $data['name'],
                        invitedBy: $invitedBy,
                    );

                    Notification::make()
                        ->title('Invitation sent')
                        ->body('Portal invitation email has been sent to '.$data['email'])
                        ->success()
                        ->send();
                }),
            ActionGroup::make([
                EditAction::make()
                    ->slideOver(),
                DeleteAction::make(),
            ]),
        ];
    }
}
