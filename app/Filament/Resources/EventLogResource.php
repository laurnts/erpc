<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ActorType;
use App\Filament\Resources\EventLogResource\Pages\ListEventLogs;
use App\Models\ActivityLog;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class EventLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $recordTitleAttribute = 'description';

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 18;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Event Logs';

    protected static ?string $modelLabel = 'event log';

    protected static ?string $slug = 'event-logs';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y H:i')
                    ->description(fn (ActivityLog $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable(),
                TextColumn::make('actor_type')
                    ->label('Actor')
                    ->badge()
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('User')
                    ->placeholder('System'),
                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? ucfirst($state) : '—'),
                TextColumn::make('subject_type')
                    ->label('Record')
                    ->formatStateUsing(fn (?string $state, ActivityLog $record): string => $state !== null
                        ? Str::headline($state).' #'.$record->subject_id
                        : '—')
                    ->searchable(),
                TextColumn::make('changed_fields')
                    ->label('Fields')
                    ->state(fn (ActivityLog $record): int => count((array) $record->properties->get('attributes', [])))
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('actor_type')
                    ->label('Actor')
                    ->options(ActorType::class)
                    ->multiple(),
                SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
                SelectFilter::make('subject_type')
                    ->label('Record type')
                    ->options(fn (): array => self::recordTypeOptions()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading('Event details')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (ActivityLog $record): View => view('filament.event-log-detail', [
                        'activity' => $record->loadMissing(['causer', 'subject']),
                    ])),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventLogs::route('/'),
        ];
    }

    /**
     * @return Builder<ActivityLog>
     */
    public static function getEloquentQuery(): Builder
    {
        $team = Filament::getTenant();

        /** @var Builder<ActivityLog> $query */
        $query = parent::getEloquentQuery()->with(['causer', 'subject']);

        if ($team === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($team): void {
            $builder->where('team_id', $team->getKey())->orWhereNull('team_id');
        });
    }

    /**
     * Distinct record types present in the current team's log, for the filter.
     *
     * @return array<string, string>
     */
    private static function recordTypeOptions(): array
    {
        return self::getEloquentQuery()
            ->getQuery()
            ->select('subject_type')
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->mapWithKeys(fn (string $type): array => [$type => Str::headline($type)])
            ->all();
    }
}
