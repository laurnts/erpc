<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberResource\Pages;

use App\Actions\Teams\UpdateTeamMemberRole;
use App\Enums\CentralPurchasingRole;
use App\Filament\Resources\MemberResource;
use App\Filament\Resources\MemberResource\RelationManagers\BuyersRelationManager;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

final class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        $membership = $this->getRecord();
        $team = Filament::getTenant();

        return [
            ActionGroup::make([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->slideOver()
                    ->modalWidth(\Filament\Support\Enums\Width::TwoExtraLarge)
                    ->form([
                        FileUpload::make('profile_photo_path')
                            ->label('Photo')
                            ->avatar()
                            ->image()
                            ->imageEditor()
                            ->disk(config('jetstream.profile_photo_disk'))
                            ->directory('profile-photos')
                            ->visibility('public')
                            ->formatStateUsing(fn (): ?string => $membership->user->profile_photo_path),
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
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
                            ->default(fn (): string => $membership->role)
                            ->live(),
                        Select::make('central_purchasing_role')
                            ->label('Central Purchasing Role')
                            ->options(fn (): array => collect(CentralPurchasingRole::cases())
                                ->mapWithKeys(fn (CentralPurchasingRole $role): array => [
                                    $role->value => $role->getLabel(),
                                ])
                                ->toArray())
                            ->required(fn ($get) => $get('role') === 'central_purchasing')
                            ->visible(fn ($get) => $get('role') === 'central_purchasing')
                            ->helperText('Select the specific role for this Central Purchasing team member.')
                            ->live(),
                        Toggle::make('is_approver')
                            ->label('Is Approver')
                            ->visible(fn ($get) => $get('role') === 'central_purchasing' && $get('central_purchasing_role') === CentralPurchasingRole::FINANCE->value)
                            ->helperText('Only finance users marked as approvers can approve credit limit increase requests.')
                            ->default(false),
                    ])
                    ->fillForm(function () use ($membership): array {
                        return [
                            'name' => $membership->user->name,
                            'profile_photo_path' => $membership->user->profile_photo_path,
                            'role' => $membership->role,
                            'central_purchasing_role' => $membership->central_purchasing_role?->value,
                            'is_approver' => $membership->is_approver ?? false,
                        ];
                    })
                    ->action(function (array $data) use ($membership, $team): void {
                        $user = $membership->user;

                        // Update profile photo if provided
                        if (isset($data['profile_photo_path'])) {
                            if ($data['profile_photo_path']) {
                                $user->updateProfilePhoto($data['profile_photo_path']);
                            } else {
                                $user->deleteProfilePhoto();
                            }
                        }

                        // Update user information
                        $userData = [
                            'name' => $data['name'],
                        ];

                        $user->forceFill($userData)->save();

                        // Update role and central_purchasing_role
                        if (isset($data['role'])) {
                            app(UpdateTeamMemberRole::class)->execute(
                                $team,
                                $membership,
                                $data['role'],
                                $data['central_purchasing_role'] ?? null,
                                $data['is_approver'] ?? false,
                            );

                            // Refresh the membership record to reflect pivot changes
                            $membership->refresh();
                            $membership->load('user', 'team');
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Member updated')
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => Gate::check('updateTeamMember', $team)),
                Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove Team Member')
                    ->modalDescription(fn () => "Are you sure you want to remove {$membership->user->email} from the team?")
                    ->action(function () use ($membership, $team): void {
                        app(\App\Actions\Jetstream\RemoveTeamMember::class)->remove(
                            auth()->user(),
                            $team,
                            $membership->user
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Team member removed')
                            ->success()
                            ->send();

                        $this->redirect(MemberResource::getUrl('index'));
                    })
                    ->visible(fn () => auth()->id() !== $membership->user_id &&
                        Gate::check('removeTeamMember', $team)
                    ),
            ]),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Flex::make([
                    Section::make('Member Information')
                        ->schema([
                            ImageEntry::make('user.profile_photo_path')
                                ->label('')
                                ->disk(config('jetstream.profile_photo_disk'))
                                ->defaultImageUrl(fn (): string => Filament::getUserAvatarUrl($this->getRecord()->user))
                                ->circular()
                                ->height(60)
                                ->grow(false),
                            TextEntry::make('user.name')
                                ->label('Name')
                                ->weight('bold')
                                ->size(\Filament\Support\Enums\TextSize::Large),
                            TextEntry::make('user.email')
                                ->label('Email')
                                ->copyable()
                                ->icon('heroicon-m-envelope'),
                            TextEntry::make('role')
                                ->label('Role')
                                ->badge()
                                ->formatStateUsing(fn (): string => $this->getRecord()->roleName)
                                ->color(fn (): string => match ($this->getRecord()->role) {
                                    'admin' => 'danger',
                                    'editor' => 'primary',
                                    'central_purchasing' => 'success',
                                    default => 'gray',
                                }),
                            // TextEntry::make('central_purchasing_role')
                            //     ->label('Central Purchasing Role')
                            //     ->badge()
                            //     ->formatStateUsing(fn (): ?string => $this->getRecord()->central_purchasing_role?->getLabel())
                            //     ->visible(fn (): bool => $this->getRecord()->role === 'central_purchasing' && $this->getRecord()->central_purchasing_role !== null),
                        ])
                        ->columns(1),
                    Section::make('Team Details')
                        ->schema([
                            TextEntry::make('team.name')
                                ->label('Team Name'),
                            TextEntry::make('created_at')
                                ->label('Joined')
                                ->dateTime(),
                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime(),
                        ])
                        ->grow(false),
                ])->columnSpan('full'),
            ]);
    }

    public function getRelationManagers(): array
    {
        $membership = $this->getRecord();

        $managers = [];

        // Only show Buyers relation manager for Key Account role
        if ($membership->role === 'central_purchasing' &&
            $membership->central_purchasing_role === CentralPurchasingRole::KEY_ACCOUNT) {
            $managers[] = BuyersRelationManager::class;
        }

        return $managers;
    }
}
