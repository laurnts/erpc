<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UnitOfMeasureResource\Pages\CreateUnitOfMeasure;
use App\Filament\Resources\UnitOfMeasureResource\Pages\ListUnitOfMeasures;
use App\Filament\Resources\UnitOfMeasureResource\Pages\ViewUnitOfMeasure;
use App\Models\UnitOfMeasure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class UnitOfMeasureResource extends Resource
{
    protected static ?string $model = UnitOfMeasure::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 14;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    /**
     * Get the base form fields for creating/editing a unit of measure.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormSchema(): array
    {
        return [
            TextInput::make('code')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $record) => $rule->where('team_id', $record->team_id ?? Filament::getTenant()?->id))
                ->helperText('A unique identifier for this unit (e.g., pcs, kg, m)'),
            TextInput::make('label')
                ->required()
                ->maxLength(255)
                ->helperText('Display name for this unit (e.g., Pieces, Kilograms, Meters)'),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->helperText('Order in which units appear in lists'),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Inactive units will not appear in selection lists'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::getFormSchema())
            ->columns(1)
            ->inlineLabel();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnitOfMeasures::route('/'),
            'create' => CreateUnitOfMeasure::route('/create'),
            'view' => ViewUnitOfMeasure::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'label'];
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }
}
