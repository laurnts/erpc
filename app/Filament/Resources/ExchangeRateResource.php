<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ExchangeRateResource\Pages\ListExchangeRates;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 12;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    /**
     * Get the base form fields for creating/editing an exchange rate.
     * Used both in main form and inline create modals.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormSchema(): array
    {
        return [
            Select::make('from_currency_id')
                ->label('From Currency')
                ->relationship(
                    'fromCurrency',
                    'name',
                    modifyQueryUsing: fn ($query) => $query->where('is_active', true)
                )
                ->getOptionLabelFromRecordUsing(fn (Currency $record): string => "{$record->code} - {$record->name}")
                ->required()
                ->searchable()
                ->preload()
                ->createOptionForm(CurrencyResource::getFormSchema(excludeDefaultField: true))
                ->createOptionUsing(function (array $data): int {
                    /** @var Currency $currency */
                    $currency = Currency::create($data);

                    return $currency->id;
                }),
            Select::make('to_currency_id')
                ->label('To Currency')
                ->relationship(
                    'toCurrency',
                    'name',
                    modifyQueryUsing: fn ($query) => $query->where('is_active', true)
                )
                ->getOptionLabelFromRecordUsing(fn (Currency $record): string => "{$record->code} - {$record->name}")
                ->required()
                ->searchable()
                ->preload()
                ->different('from_currency_id')
                ->createOptionForm(CurrencyResource::getFormSchema(excludeDefaultField: true))
                ->createOptionUsing(function (array $data): int {
                    /** @var Currency $currency */
                    $currency = Currency::create($data);

                    return $currency->id;
                }),
            TextInput::make('rate')
                ->required()
                ->numeric()
                ->minValue(0.0000000001)
                ->step(0.0000000001)
                ->helperText('Exchange rate from source to target currency'),
            DatePicker::make('effective_date')
                ->required()
                ->default(now())
                ->native(false),
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
            ->description('Manage exchange rates for multi-currency transactions.')
            ->columns([
                TextColumn::make('fromCurrency.code')
                    ->label('From')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('toCurrency.code')
                    ->label('To')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('rate')
                    ->sortable()
                    ->numeric(decimalPlaces: 6),
                TextColumn::make('inverse_rate')
                    ->label('Inverse')
                    ->state(function (ExchangeRate $record): string {
                        $rate = (float) $record->rate;
                        if ($rate === 0.0) {
                            return '-';
                        }

                        return sprintf(
                            '1 %s = %s %s',
                            $record->toCurrency->code,
                            number_format(1 / $rate, 2),
                            $record->fromCurrency->code
                        );
                    })
                    ->color('gray'),
                TextColumn::make('effective_date')
                    ->label('Effective Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Source')
                    ->state(fn (ExchangeRate $record): string => $record->creator->name ?? 'Manual Entry')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Last Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('effective_date', 'desc')
            ->filters([
                SelectFilter::make('from_currency_id')
                    ->label('From Currency')
                    ->options(fn () => Currency::query()
                        ->where('is_active', true)
                        ->pluck('code', 'id')
                        ->all()),
                SelectFilter::make('to_currency_id')
                    ->label('To Currency')
                    ->options(fn () => Currency::query()
                        ->where('is_active', true)
                        ->pluck('code', 'id')
                        ->all()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
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
            'index' => ListExchangeRates::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }
}
