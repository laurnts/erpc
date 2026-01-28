<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;
use Override;

final class ListMembers extends ListRecords
{
    /** @var class-string<MemberResource> */
    protected static string $resource = MemberResource::class;

    protected string $view = 'filament.resources.member-resource.pages.list-members';

    /**
     * Get the actions available on the resource index header.
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addMember')
                ->label('Add Team Member')
                ->icon('heroicon-o-plus')
                ->size(Size::Small)
                ->modalHeading('Add Team Member')
                ->modalSubmitActionLabel('Add')
                ->form([
                    \Filament\Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->helperText('Please provide the email address of the person you would like to add to this team.'),
                    \Filament\Forms\Components\Radio::make('role')
                        ->label('Role')
                        ->options([
                            'admin' => 'Administrator',
                            'editor' => 'Editor',
                        ])
                        ->descriptions([
                            'admin' => 'Administrator users can perform any action.',
                            'editor' => 'Editor users have the ability to read, create, and update.',
                        ])
                        ->required()
                        ->default('editor'),
                ])
                ->action(function (array $data): void {
                    $team = Filament::getTenant();
                    app(\App\Actions\Jetstream\InviteTeamMember::class)->invite(
                        auth()->user(),
                        $team,
                        $data['email'],
                        $data['role']
                    );
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Team invitation sent')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => \Illuminate\Support\Facades\Gate::check('addTeamMember', Filament::getTenant())),
        ];
    }

    public function getPendingInvitationsTeam()
    {
        $tenant = Filament::getTenant();
        
        if (! auth()->user()->hasTeamRole($tenant, 'admin')) {
            return null;
        }

        return $tenant;
    }
}
