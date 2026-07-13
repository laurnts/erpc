<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ExchangeRateResource\Pages\ListExchangeRates;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

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
                    modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)
                )
                ->getOptionLabelFromRecordUsing(fn (?Currency $record): string => $record instanceof \App\Models\Currency ? "{$record->code} - {$record->name}" : '')
                ->required()

                ->preload()
                ->selectablePlaceholder(false)
                ->live()
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
                    modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)
                )
                ->getOptionLabelFromRecordUsing(fn (?Currency $record): string => $record instanceof \App\Models\Currency ? "{$record->code} - {$record->name}" : '')
                ->required()

                ->preload()
                ->selectablePlaceholder(false)
                ->live()
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
                ->live(onBlur: true)
                ->helperText('Exchange rate from source to target currency'),
            DatePicker::make('effective_date')
                ->required()
                ->default(now())
                ->native(false),
            Placeholder::make('rate_preview')
                ->label('Exchange Rate Preview')
                ->content(function (Get $get): HtmlString {
                    $fromCurrencyId = $get('from_currency_id');
                    $toCurrencyId = $get('to_currency_id');
                    $rate = $get('rate');

                    if ($fromCurrencyId === null || $toCurrencyId === null || $rate === null || $rate === '' || (float) $rate <= 0) {
                        return new HtmlString('<span class="text-gray-400">Select currencies and enter rate to see preview</span>');
                    }

                    /** @var Currency|null $fromCurrency */
                    $fromCurrency = Currency::query()->find($fromCurrencyId);
                    /** @var Currency|null $toCurrency */
                    $toCurrency = Currency::query()->find($toCurrencyId);

                    if ($fromCurrency === null || $toCurrency === null) {
                        return new HtmlString('<span class="text-gray-400">Select currencies and enter rate to see preview</span>');
                    }

                    $rateFloat = (float) $rate;

                    $preview = sprintf(
                        '1 %s = %s',
                        $fromCurrency->code,
                        $toCurrency->format($rateFloat)
                    );

                    return new HtmlString(sprintf(
                        '<div class="font-semibold text-primary-600 dark:text-primary-400">%s</div>',
                        $preview
                    ));
                })
                ->live(),
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
                TextColumn::make('rate_preview')
                    ->label('Rate')
                    ->state(fn (ExchangeRate $record): string => sprintf(
                        '1 %s = %s',
                        $record->fromCurrency->code,
                        $record->toCurrency->format((float) $record->rate)
                    ))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('rate', $direction)),
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
