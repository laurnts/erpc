<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\CustomerPortal\ApprovePortalRegistration;
use App\Actions\CustomerPortal\RejectPortalRegistration;
use App\Enums\PortalRegistrationStatus;
use App\Filament\Resources\PortalRegistrationRequestResource\Pages\ListPortalRegistrationRequests;
use App\Models\PortalRegistrationRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

final class PortalRegistrationRequestResource extends Resource
{
    protected static ?string $model = PortalRegistrationRequest::class;

    protected static ?string $recordTitleAttribute = 'email';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Approval';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Registrations';

    protected static ?string $modelLabel = 'Registration';

    protected static ?string $pluralModelLabel = 'Registrations';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getEloquentQuery()
            ->where('status', PortalRegistrationStatus::Pending)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('message')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Applied At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('decidedBy.name')
                    ->label('Decided By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('decided_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PortalRegistrationStatus::class),
            ])
            ->recordAction('view')
            ->recordActions([
                ViewAction::make()
                    ->modalHeading('Registration details')
                    ->schema(fn (PortalRegistrationRequest $record): array => self::getViewSchema($record)),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve registration')
                    ->modalDescription('This creates a buyer company, a user account, and active customer portal access, and notifies the applicant by email.')
                    ->visible(fn (PortalRegistrationRequest $record): bool => $record->isPending())
                    ->action(function (PortalRegistrationRequest $record): void {
                        /** @var User $approver */
                        $approver = auth()->user();

                        try {
                            app(ApprovePortalRegistration::class)->execute($record, $approver);
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Approval failed')
                                ->body(implode(' ', collect($exception->errors())->flatten()->all()))
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Application approved')
                            ->body('Portal access has been created and the applicant notified.')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject registration')
                    ->modalDescription('The applicant will be notified by email. No records are created.')
                    ->visible(fn (PortalRegistrationRequest $record): bool => $record->isPending())
                    ->action(function (PortalRegistrationRequest $record): void {
                        /** @var User $decidedBy */
                        $decidedBy = auth()->user();

                        try {
                            app(RejectPortalRegistration::class)->execute($record, $decidedBy);
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Rejection failed')
                                ->body(implode(' ', collect($exception->errors())->flatten()->all()))
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Application rejected')
                            ->body('The applicant has been notified.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Infolists\Components\Entry>
     */
    private static function getViewSchema(PortalRegistrationRequest $record): array
    {
        return [
            Section::make('Application')
                ->schema([
                    TextEntry::make('name')
                        ->state($record->name),
                    TextEntry::make('email')
                        ->state($record->email),
                    TextEntry::make('company_name')
                        ->label('Company')
                        ->state($record->company_name),
                    TextEntry::make('phone')
                        ->state($record->phone)
                        ->placeholder('-'),
                    TextEntry::make('message')
                        ->state($record->message)
                        ->placeholder('-')
                        ->columnSpanFull(),
                    TextEntry::make('status')
                        ->state($record->status)
                        ->badge(),
                    TextEntry::make('created_at')
                        ->label('Applied at')
                        ->state($record->created_at)
                        ->dateTime(),
                    TextEntry::make('decidedBy.name')
                        ->label('Decided by')
                        ->state($record->decidedBy?->name)
                        ->placeholder('-')
                        ->visible($record->decided_at !== null),
                    TextEntry::make('decided_at')
                        ->label('Decided at')
                        ->state($record->decided_at)
                        ->dateTime()
                        ->visible($record->decided_at !== null),
                ])
                ->columns(2),
        ];
    }

    /**
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPortalRegistrationRequests::route('/'),
        ];
    }
}
