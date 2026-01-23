<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages\CreateCurrency;
use App\Filament\Resources\CurrencyResource\Pages\ListCurrencies;
use App\Models\Currency;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 11;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    /**
     * Get the base form fields for creating/editing a currency.
     * Used both in main form and inline create modals.
     *
     * @param  bool  $excludeDefaultField  Exclude is_default field for inline creates
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormSchema(bool $excludeDefaultField = false): array
    {
        $fields = [
            TextInput::make('code')
                ->required()
                ->maxLength(3)
                ->minLength(3)
                ->formatStateUsing(fn (?string $state): ?string => $state !== null ? strtoupper($state) : null)
                ->dehydrateStateUsing(fn (string $state): string => strtoupper($state))
                ->unique(ignoreRecord: true)
                ->helperText('ISO 4217 currency code (e.g., USD, EUR)'),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('symbol')
                ->required()
                ->maxLength(10),
            TextInput::make('decimal_places')
                ->numeric()
                ->required()
                ->default(2)
                ->minValue(0)
                ->maxValue(10),
            TextInput::make('thousands_separator')
                ->maxLength(1)
                ->default(',')
                ->helperText('Character for thousands grouping (e.g., comma or dot)'),
            TextInput::make('decimal_separator')
                ->maxLength(1)
                ->default('.')
                ->helperText('Character for decimal point (e.g., dot or comma)'),
            Select::make('symbol_position')
                ->options([
                    'before' => 'Before amount (e.g., $100)',
                    'after' => 'After amount (e.g., 100 EUR)',
                ])
                ->default('before'),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ];

        // Add is_default field only in main form (not inline creates)
        if (! $excludeDefaultField) {
            $fields[] = Toggle::make('is_default')
                ->label('Default Currency')
                ->default(false)
                ->helperText('Only one currency can be set as default');
        }

        return $fields;
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
                    ->weight('bold'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('symbol')
                    ->sortable(),
                TextColumn::make('decimal_places')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('format_preview')
                    ->label('Format Preview')
                    ->state(fn (Currency $record): string => $record->format(1000))
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->defaultSort('code', 'asc')
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->slideOver(),
                    DeleteAction::make(),
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
            'index' => ListCurrencies::route('/'),
            'create' => CreateCurrency::route('/create'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name'];
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }
}
