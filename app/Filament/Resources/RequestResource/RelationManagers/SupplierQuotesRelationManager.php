<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\SupplierQuoteStatus;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\TaxCode;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class SupplierQuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'supplierQuotes';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-currency-dollar';

    public function form(Schema $schema): Schema
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $schema
            ->components([
                Section::make('Quote Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('supplier_id')
                                    ->label('Supplier')
                                    ->options(
                                        Company::query()
                                            ->where('team_id', $request->team_id)
                                            ->where('is_supplier', true)
                                            ->where('is_active', true)
                                            ->orderBy('name')
                                            ->get()
                                            ->mapWithKeys(fn (Company $supplier): array => [
                                                $supplier->getKey() => "[{$supplier->code}] {$supplier->name}",
                                            ])
                                    )
                                    ->searchable()
                                    ->required(),
                                TextInput::make('supplier_reference')
                                    ->label('Supplier Reference')
                                    ->maxLength(255)
                                    ->helperText('Supplier\'s quote/reference number'),
                                Select::make('status')
                                    ->options(SupplierQuoteStatus::class)
                                    ->default(SupplierQuoteStatus::PENDING)
                                    ->required(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Select::make('currency_id')
                                    ->label('Currency')
                                    ->options(
                                        Currency::query()
                                            ->where('is_active', true)
                                            ->orderBy('code')
                                            ->get()
                                            ->mapWithKeys(fn (Currency $currency): array => [
                                                $currency->getKey() => "{$currency->code} - {$currency->name}",
                                            ])
                                    )
                                    ->default(function (): ?int {
                                        $defaultCode = auth()->user()->currentTeam?->getErpSettings()->default_currency ?? 'USD';

                                        return Currency::query()->where('code', $defaultCode)->where('is_active', true)->value('id');
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                                        if ($state === null) {
                                            return;
                                        }
                                        // Could auto-fetch exchange rate here if desired
                                    }),
                                TextInput::make('exchange_rate')
                                    ->label('Exchange Rate')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->step(0.00000001)
                                    ->helperText('Rate to convert to base currency'),
                                DatePicker::make('quoted_at')
                                    ->label('Quote Date')
                                    ->default(now())
                                    ->required(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('valid_until')
                                    ->label('Valid Until')
                                    ->helperText('Leave empty for default validity period'),
                                Placeholder::make('quote_number_display')
                                    ->label('Quote Number')
                                    ->content(fn (?SupplierQuote $record): string => $record?->quote_number ?? 'Auto-generated'),
                            ]),
                    ]),

                Section::make('Line Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        Select::make('request_item_id')
                                            ->label('Request Item')
                                            ->options(fn (): array => $request->items()
                                                ->get()
                                                ->mapWithKeys(fn ($item): array => [
                                                    $item->getKey() => $item->display_text,
                                                ])
                                                ->all())
                                            ->searchable()
                                            ->columnSpan(4)
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, ?int $state) use ($request): void {
                                                if ($state === null) {
                                                    return;
                                                }
                                                $requestItem = $request->items()->find($state);
                                                if ($requestItem !== null) {
                                                    $set('article_id', $requestItem->article_id);
                                                    $set('description', $requestItem->description);
                                                    $set('quantity', $requestItem->quantity);
                                                    $set('unit', $requestItem->unit);
                                                }
                                            }),
                                        TextInput::make('description')
                                            ->required()
                                            ->columnSpan(4),
                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->required()
                                            ->default(1)
                                            ->step(0.0001)
                                            ->columnSpan(2),
                                        TextInput::make('unit')
                                            ->default('pcs')
                                            ->columnSpan(2),
                                    ]),
                                Grid::make(12)
                                    ->schema([
                                        TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->step(0.0001)
                                            ->columnSpan(3)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                        Select::make('tax_code_id')
                                            ->label('Tax Code')
                                            ->options(fn (): array => TaxCode::query()
                                                ->where('team_id', $request->team_id)
                                                ->where('is_active', true)
                                                ->orderBy('sort_order')
                                                ->get()
                                                ->mapWithKeys(fn (TaxCode $taxCode): array => [
                                                    $taxCode->getKey() => $taxCode->display_name,
                                                ])
                                                ->all())
                                            ->searchable()
                                            ->columnSpan(3)
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, Get $get, ?int $state): void {
                                                if ($state === null) {
                                                    $set('tax_rate', 0);
                                                } else {
                                                    $taxCode = TaxCode::find($state);
                                                    if ($taxCode !== null) {
                                                        $set('tax_rate', $taxCode->rate);
                                                        $set('is_tax_inclusive', $taxCode->is_inclusive_default);
                                                    }
                                                }
                                                $this->calculateItemTotals($set, $get);
                                            }),
                                        Checkbox::make('is_tax_inclusive')
                                            ->label('Tax Inclusive')
                                            ->inline(false)
                                            ->columnSpan(2)
                                            ->live()
                                            ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                        TextInput::make('tax_rate')
                                            ->label('Tax %')
                                            ->numeric()
                                            ->default(0)
                                            ->step(0.01)
                                            ->columnSpan(2)
                                            ->disabled()
                                            ->dehydrated(),
                                        TextInput::make('line_total')
                                            ->label('Line Total')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(2),
                                    ]),
                                Textarea::make('notes')
                                    ->rows(1)
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->defaultItems(0)
                            ->addActionLabel('Add Line Item')
                            ->reorderable()
                            ->orderColumn('sort_order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['description'] ?? null),
                    ]),

                Section::make('Summary')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(fn (?SupplierQuote $record): string => $record instanceof \App\Models\SupplierQuote
                                        ? number_format((float) $record->subtotal, 2)
                                        : '0.00'),
                                Placeholder::make('tax_total_display')
                                    ->label('Tax Total')
                                    ->content(fn (?SupplierQuote $record): string => $record instanceof \App\Models\SupplierQuote
                                        ? number_format((float) $record->tax_total, 2)
                                        : '0.00'),
                                Placeholder::make('total_display')
                                    ->label('Total')
                                    ->content(fn (?SupplierQuote $record): string => $record instanceof \App\Models\SupplierQuote
                                        ? number_format((float) $record->total, 2)
                                        : '0.00'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('subtotal_base_display')
                                    ->label('Subtotal (Base)')
                                    ->content(fn (?SupplierQuote $record): string => $record instanceof \App\Models\SupplierQuote
                                        ? number_format((float) $record->subtotal_base, 2)
                                        : '0.00'),
                                Placeholder::make('tax_total_base_display')
                                    ->label('Tax Total (Base)')
                                    ->content(fn (?SupplierQuote $record): string => $record instanceof \App\Models\SupplierQuote
                                        ? number_format((float) $record->tax_total_base, 2)
                                        : '0.00'),
                                Placeholder::make('total_base_display')
                                    ->label('Total (Base)')
                                    ->content(fn (?SupplierQuote $record): string => $record instanceof \App\Models\SupplierQuote
                                        ? number_format((float) $record->total_base, 2)
                                        : '0.00'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Supplier Notes')
                            ->rows(2),
                        Textarea::make('internal_notes')
                            ->label('Internal Notes')
                            ->rows(2)
                            ->helperText('Not visible to supplier'),
                    ])
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('quote_number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('quote_number')
                    ->label('Quote #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier_reference')
                    ->label('Supplier Ref')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('currency.code')
                    ->label('Currency')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->description(fn (SupplierQuote $record): string => $record->currency?->code ?? ''),
                TextColumn::make('total_base')
                    ->label('Total (Base)')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('exchange_rate')
                    ->label('Rate')
                    ->numeric(decimalPlaces: 4)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quoted_at')
                    ->label('Quote Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date()
                    ->sortable()
                    ->color(fn (SupplierQuote $record): string => $record->is_expired ? 'danger' : 'success'),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SupplierQuoteStatus::class),
                SelectFilter::make('supplier')
                    ->relationship('supplier', 'name'),
                SelectFilter::make('currency_id')
                    ->label('Currency')
                    ->options(fn () => \App\Models\Currency::query()
                        ->where('is_active', true)
                        ->pluck('code', 'id')
                        ->all()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('select')
                    ->label('Select')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SupplierQuote $record): bool => $record->status === SupplierQuoteStatus::PENDING)
                    ->requiresConfirmation()
                    ->modalHeading('Select this quote?')
                    ->modalDescription('This will mark this supplier quote as selected.')
                    ->action(function (SupplierQuote $record): void {
                        $record->markAsSelected();
                        Notification::make()
                            ->title('Quote selected')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SupplierQuote $record): bool => $record->status === SupplierQuoteStatus::PENDING)
                    ->requiresConfirmation()
                    ->modalHeading('Reject this quote?')
                    ->modalDescription('This will mark this supplier quote as rejected.')
                    ->action(function (SupplierQuote $record): void {
                        $record->markAsRejected();
                        Notification::make()
                            ->title('Quote rejected')
                            ->warning()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Calculate item totals based on form values.
     */
    private function calculateItemTotals(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);
        $isTaxInclusive = (bool) $get('is_tax_inclusive');

        $lineAmount = $quantity * $unitPrice;

        if ($isTaxInclusive) {
            $lineTotal = $lineAmount;
            $lineSubtotal = $taxRate > 0 ? $lineAmount / (1 + $taxRate / 100) : $lineAmount;
            $lineTax = $lineTotal - $lineSubtotal;
        } else {
            $lineSubtotal = $lineAmount;
            $lineTax = $lineAmount * $taxRate / 100;
            $lineTotal = $lineSubtotal + $lineTax;
        }

        $set('line_subtotal', round($lineSubtotal, 4));
        $set('line_tax', round($lineTax, 4));
        $set('line_total', round($lineTotal, 4));
    }
}
