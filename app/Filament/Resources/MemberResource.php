<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Jetstream\RemoveTeamMember;
use App\Enums\CentralPurchasingRole;
use App\Filament\Resources\MemberResource\Pages\ListMembers;
use App\Models\Membership;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class MemberResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static ?string $recordTitleAttribute = 'user.email';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return 'Team Members';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Membership $record): string => MemberResource::getUrl('view', ['record' => $record]))
            ->columns([
                ImageColumn::make('user.profile_photo_path')
                    ->label('')
                    ->disk(config('jetstream.profile_photo_disk'))
                    ->defaultImageUrl(fn (Membership $record): string => Filament::getUserAvatarUrl($record->user))
                    ->circular()
                    ->size(32),
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (Membership $record): string => $record->roleName)
                    ->color(fn (Membership $record): string => match ($record->role) {
                        'admin' => 'danger',
                        'editor' => 'primary',
                        'central_purchasing' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('is_approver')
                    ->label('Approver')
                    ->badge()
                    ->getStateUsing(fn (Membership $record): ?string => ($record->role === 'central_purchasing' &&
                         $record->central_purchasing_role === CentralPurchasingRole::FINANCE &&
                         $record->is_approver) ? 'Approver' : null
                    )
                    ->color('success')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'admin' => 'Administrator',
                        'editor' => 'Editor',
                        'central_purchasing' => 'Central Purchasing',
                    ]),
            ])
            ->actions([
                Action::make('leaveTeam')
                    ->label('Leave Team')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('danger')
                    ->visible(fn (Membership $record): bool => auth()->id() === $record->user_id)
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to leave this team? You will lose access immediately and will need a new invitation to rejoin.')
                    ->action(function (Membership $record, Component $livewire): void {
                        try {
                            app(RemoveTeamMember::class)->remove(auth()->user(), Filament::getTenant(), $record->user);

                            $livewire->redirect(Filament::getHomeUrl());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title($e->validator->errors()->first())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    /**
     * @return Builder<Membership>
     */
    public static function getEloquentQuery(): Builder
    {
        $team = Filament::getTenant();

        return parent::getEloquentQuery()
            ->where('team_id', $team->id)
            ->with('user');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'view' => \App\Filament\Resources\MemberResource\Pages\ViewMember::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['user.name', 'user.email'];
    }
}
