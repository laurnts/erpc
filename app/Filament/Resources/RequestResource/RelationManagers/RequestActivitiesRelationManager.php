<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Models\RequestActivity;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class RequestActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Activity Log';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clock';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('activity_type')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('activity_type')
                    ->label('Activity')
                    ->badge()
                    ->icon(fn (RequestActivity $record): string => $record->activity_type->getIcon())
                    ->color(fn (RequestActivity $record): string => $record->activity_type->getColor()),
                TextColumn::make('description')
                    ->wrap()
                    ->limit(100),
                TextColumn::make('metadata')
                    ->label('Details')
                    ->state(fn (RequestActivity $record): ?string => $this->formatMetadata($record->metadata))
                    ->color('gray')
                    ->wrap()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('System'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->groups([
                \Filament\Tables\Grouping\Group::make('created_at')
                    ->label('Date')
                    ->date(),
            ])
            ->defaultGroup('created_at');
    }

    /**
     * Format metadata array for display.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    private function formatMetadata(?array $metadata): ?string
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        $parts = [];
        foreach ($metadata as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            }
            $parts[] = ucfirst(str_replace('_', ' ', $key)).': '.$value;
        }

        return implode(' | ', $parts);
    }
}
