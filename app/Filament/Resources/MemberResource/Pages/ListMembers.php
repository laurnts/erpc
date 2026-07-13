<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberResource\Pages;

use App\Enums\CentralPurchasingRole;
use App\Filament\Resources\MemberResource;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Size;
use Illuminate\Database\Eloquent\Model;
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
                    Radio::make('role')
                        ->label('Role')
                        ->options([
                            'admin' => 'Administrator',
                            'editor' => 'Editor',
                            'central_purchasing' => 'Central Purchasing',
                        ])
                        ->descriptions([
                            'admin' => 'Administrator users can perform any action.',
                            'editor' => 'Editor users have the ability to read, create, and update.',
                            'central_purchasing' => 'Central Purchasing users have the ability to read, create, and update.',
                        ])
                        ->required()
                        ->default('editor')
                        ->live(),
                    Select::make('central_purchasing_role')
                        ->label('Central Purchasing Role')
                        ->options(fn (): array => collect(CentralPurchasingRole::cases())
                            ->mapWithKeys(fn (CentralPurchasingRole $role): array => [
                                $role->value => $role->getLabel(),
                            ])
                            ->toArray())
                        ->required(fn (Get $get): bool => $get('role') === 'central_purchasing')
                        ->visible(fn (Get $get): bool => $get('role') === 'central_purchasing')
                        ->helperText('Select the specific role for this Central Purchasing team member.'),
                ])
                ->action(function (array $data): void {
                    $team = Filament::getTenant();
                    app(\App\Actions\Jetstream\InviteTeamMember::class)->invite(
                        auth()->user(),
                        $team,
                        $data['email'],
                        $data['role'],
                        $data['central_purchasing_role'] ?? null
                    );

                    \Filament\Notifications\Notification::make()
                        ->title('Team invitation sent')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => \Illuminate\Support\Facades\Gate::check('addTeamMember', Filament::getTenant())),
        ];
    }

    public function getPendingInvitationsTeam(): ?Model
    {
        $tenant = Filament::getTenant();

        if (! auth()->user()->hasTeamRole($tenant, 'admin')) {
            return null;
        }

        return $tenant;
    }

    public function getTeamOwner(): ?User
    {
        $team = Filament::getTenant();

        if (! $team instanceof Team) {
            return null;
        }

        $owner = $team->owner;

        return $owner instanceof User ? $owner : null;
    }
}
