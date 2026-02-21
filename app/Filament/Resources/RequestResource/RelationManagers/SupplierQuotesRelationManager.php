<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\QEStatus;
use App\Enums\RequestStage;
use App\Enums\SupplierQuoteStatus;
use App\Filament\Resources\QuotationEvaluationResource;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\Company;
use App\Models\Currency;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

final class SupplierQuotesRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'supplierQuotes';

    protected static ?string $title = 'Supplier Quotes';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-currency-dollar';

    protected static function getAssociatedStage(): RequestStage
    {
        return RequestStage::AWAITING_SUPPLIER_RESPONSE;
    }

    protected static function getBaseTabTitle(): string
    {
        return 'Supplier Quotes';
    }

    public function form(Schema $schema): Schema
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $schema
            ->columns(1)
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
                                    ->required()
                                    ->selectablePlaceholder(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        // Check if supplier is taxable
                                        $supplierId = $get('supplier_id');
                                        $isTaxable = true; // Default

                                        if ($supplierId !== null) {
                                            /** @var Company|null $supplier */
                                            $supplier = Company::query()->find($supplierId);
                                            $isTaxable = $supplier?->is_taxable ?? true;
                                        }

                                        // Trigger recalculation for all line items when supplier changes
                                        $items = $get('items') ?? [];
                                        foreach ($items as $index => $item) {
                                            // Get current values
                                            $quantity = (float) ($item['quantity'] ?? 1);
                                            $unitPrice = (float) ($item['unit_price'] ?? 0);

                                            if ($unitPrice > 0 && $quantity > 0) {
                                                // Clear tax values if supplier is not taxable BEFORE recalculation
                                                if (! $isTaxable) {
                                                    $set("items.{$index}.tax_rate", 0);
                                                    $set("items.{$index}.tax_code_id", null);
                                                    $set("items.{$index}.is_tax_inclusive", false);

                                                    // Directly calculate and set line_total without tax
                                                    $lineTotal = $quantity * $unitPrice;
                                                    $set("items.{$index}.line_subtotal", round($lineTotal, 4));
                                                    $set("items.{$index}.line_tax", 0);
                                                    $set("items.{$index}.line_total", round($lineTotal, 4));
                                                } else {
                                                    // Trigger recalculation by modifying unit_price slightly
                                                    // This will trigger the afterStateUpdated callback on unit_price field
                                                    $set("items.{$index}.unit_price", $unitPrice + 0.0001);
                                                    $set("items.{$index}.unit_price", $unitPrice);
                                                }
                                            }
                                        }
                                    }),
                                TextInput::make('supplier_reference')
                                    ->label('Supplier Reference')
                                    ->maxLength(255)
                                    ->helperText('Supplier\'s quote/reference number'),
                                Placeholder::make('quote_number_display')
                                    ->label('Quote Number')
                                    ->content(fn (?SupplierQuote $record): string => $record instanceof \App\Models\SupplierQuote ? ($record->quote_number ?? 'Auto-generated') : 'Auto-generated'),
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
                                        /** @var \App\Models\Team|null $team */
                                        $team = Filament::getTenant();
                                        $defaultCode = $team?->getErpSettings()->default_currency ?? 'USD';

                                        return Currency::query()->where('code', $defaultCode)->where('is_active', true)->value('id');
                                    })
                                    
                                    ->required()
                                    ->selectablePlaceholder(false)
                                    ->live()
                                    ->helperText(function (Get $get): ?string {
                                        $rate = (float) ($get('exchange_rate') ?? 1);
                                        $currencyId = $get('currency_id');

                                        if ($currencyId === null) {
                                            return null;
                                        }

                                        /** @var \App\Models\Team|null $team */
                                        $team = Filament::getTenant();
                                        $baseCurrencyCode = $team?->getErpSettings()->default_currency ?? 'USD';

                                        /** @var Currency|null $currency */
                                        $currency = Currency::query()->find($currencyId);

                                        if ($currency === null || $currency->code === $baseCurrencyCode) {
                                            return null;
                                        }

                                        $currencyCode = $currency->code;

                                        /** @var Currency|null $baseCurrency */
                                        $baseCurrency = Currency::query()->where('code', $baseCurrencyCode)->first();

                                        if ($baseCurrency === null) {
                                            return sprintf('1 %s = %s %s', $currencyCode, $baseCurrencyCode, number_format($rate, 2));
                                        }

                                        $thousandsSep = $baseCurrency->thousands_separator ?? ',';
                                        $decimalSep = $baseCurrency->decimal_separator ?? '.';
                                        $decimalPlaces = $baseCurrency->decimal_places ?? 2;
                                        $formatted = number_format($rate, $decimalPlaces, $decimalSep, $thousandsSep);

                                        if ($decimalPlaces === 0 && $baseCurrencyCode === 'IDR') {
                                            $formatted .= ',-';
                                        }

                                        return sprintf('1 %s = %s %s', $currencyCode, $baseCurrencyCode, $formatted);
                                    })
                                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                                        if ($state === null) {
                                            return;
                                        }

                                        /** @var \App\Models\Team|null $team */
                                        $team = Filament::getTenant();
                                        $baseCurrencyCode = $team?->getErpSettings()->default_currency ?? 'USD';
                                        $baseCurrency = Currency::query()->where('code', $baseCurrencyCode)->first();

                                        if ($baseCurrency === null) {
                                            $set('exchange_rate', 1);

                                            return;
                                        }

                                        if ($state === $baseCurrency->getKey()) {
                                            $set('exchange_rate', 1);

                                            return;
                                        }

                                        $exchangeRate = \App\Models\ExchangeRate::query()
                                            ->where('team_id', $team?->getKey())
                                            ->where('from_currency_id', $state)
                                            ->where('to_currency_id', $baseCurrency->getKey())
                                            ->orderByDesc('effective_date')
                                            ->first();

                                        $set('exchange_rate', $exchangeRate !== null ? $exchangeRate->rate : 1);
                                    }),
                                Hidden::make('exchange_rate')
                                    ->default(1)
                                    ->dehydrated(),
                                DatePicker::make('quoted_at')
                                    ->label('Quote Date')
                                    ->default(now())
                                    ->required(),
                                Select::make('status')
                                    ->options(SupplierQuoteStatus::class)
                                    ->default(function (?SupplierQuote $record): SupplierQuoteStatus {
                                        // If editing existing quote, check if it has prices and auto-update status
                                        if ($record instanceof \App\Models\SupplierQuote) {
                                            $hasPrices = $record->items()->where('unit_price', '>', 0)->exists();
                                            $total = (float) $record->total;
                                            
                                            // Auto-update status from PENDING to RECEIVED if prices exist
                                            if ($record->status === SupplierQuoteStatus::PENDING && ($hasPrices || $total > 0)) {
                                                return SupplierQuoteStatus::RECEIVED;
                                            }
                                            
                                            return $record->status;
                                        }
                                        
                                        return SupplierQuoteStatus::PENDING;
                                    })
                                    ->required()
                                    ->selectablePlaceholder(false),
                            ]),
                        DatePicker::make('valid_until')
                            ->label('Valid Until')
                            ->helperText('Leave empty for default validity period'),
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
                                            
                                            ->selectablePlaceholder(false)
                                            ->columnSpan(4)
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, ?int $state) use ($request): void {
                                                if ($state === null) {
                                                    return;
                                                }
                                                $requestItem = $request->items()->with('article.defaultTaxCode', 'unitOfMeasure')->find($state);
                                                if ($requestItem !== null) {
                                                    $set('article_id', $requestItem->article_id);
                                                    $set('description', $requestItem->description);
                                                    $set('quantity', $requestItem->quantity);
                                                    $set('unit_of_measure_id', $requestItem->unit_of_measure_id);

                                                    // Prefill tax code from article's default tax code
                                                    if ($requestItem->article?->default_tax_code_id !== null) {
                                                        $set('tax_code_id', $requestItem->article->default_tax_code_id);
                                                        $taxCode = $requestItem->article->defaultTaxCode;
                                                        if ($taxCode !== null) {
                                                            $set('tax_rate', $taxCode->rate);
                                                            $set('is_tax_inclusive', $taxCode->is_inclusive_default);
                                                        }
                                                    }
                                                }
                                            }),
                                        Hidden::make('article_id'),
                                        TextInput::make('description')
                                            ->required()
                                            ->columnSpan(4),
                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->required()
                                            ->disabled()
                                            ->default(1)
                                            ->step(0.0001)
                                            ->columnSpan(2)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                        Select::make('unit_of_measure_id')
                                            ->label('Unit')
                                            ->relationship('unitOfMeasure', 'label', fn ($query) => $query->where('team_id', $request->team_id)->where('is_active', true))
                                            
                                            ->preload()
                                            ->selectablePlaceholder(false)
                                            ->default(fn (): ?int => UnitOfMeasure::query()
                                                ->where('team_id', $request->team_id)
                                                ->where('code', 'pcs')
                                                ->where('is_active', true)
                                                ->value('id'))
                                            ->afterStateHydrated(function (Set $set, Get $get, ?int $state) use ($request): void {
                                                // Prefill unit from request item if not already set
                                                if ($state === null) {
                                                    $requestItemId = $get('request_item_id');
                                                    if ($requestItemId !== null) {
                                                        $requestItem = $request->items()->with('unitOfMeasure')->find($requestItemId);
                                                        if ($requestItem !== null && $requestItem->unit_of_measure_id !== null) {
                                                            $set('unit_of_measure_id', $requestItem->unit_of_measure_id);
                                                        }
                                                    }
                                                }
                                            })
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
                                            ->default(fn (): ?int => TaxCode::query()
                                                ->where('team_id', $request->team_id)
                                                ->where('is_default', true)
                                                ->where('is_active', true)
                                                ->value('id'))
                                            
                                            ->selectablePlaceholder(false)
                                            ->columnSpan(3)
                                            ->live()
                                            ->visible(fn (Get $get): bool => $this->isSupplierTaxable($get))
                                            ->afterStateHydrated(function (Set $set, Get $get, ?int $state): void {
                                                // Prefill tax code from article's default if not set
                                                if ($state === null) {
                                                    $articleId = $get('article_id');
                                                    if ($articleId !== null) {
                                                        /** @var \App\Models\Article|null $article */
                                                        $article = \App\Models\Article::query()->find($articleId);
                                                        if ($article !== null && $article->default_tax_code_id !== null) {
                                                            $set('tax_code_id', $article->default_tax_code_id);
                                                            $taxCode = $article->defaultTaxCode;
                                                            if ($taxCode !== null) {
                                                                $set('tax_rate', $taxCode->rate);
                                                                $set('is_tax_inclusive', $taxCode->is_inclusive_default);
                                                            }
                                                        }
                                                    }
                                                }
                                            })
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
                                            ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get))
                                            ->visible(fn (Get $get): bool => $this->isSupplierTaxable($get)),
                                        TextInput::make('tax_rate')
                                            ->label('Tax %')
                                            ->numeric()
                                            ->default(0)
                                            ->step(0.01)
                                            ->columnSpan(2)
                                            ->disabled()
                                            ->dehydrated()
                                            ->visible(fn (Get $get): bool => $this->isSupplierTaxable($get)),
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
                            ->addable(false)
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
                                        ? ($record->currency?->formatNumber((float) $record->subtotal) ?? number_format((float) $record->subtotal, 2))
                                        : '0,-'),
                                Placeholder::make('tax_total_display')
                                    ->label('Tax Total')
                                    ->content(fn (?SupplierQuote $record): string => $record instanceof \App\Models\SupplierQuote
                                        ? ($record->currency?->formatNumber((float) $record->tax_total) ?? number_format((float) $record->tax_total, 2))
                                        : '0,-')
                                    ->visible(fn (?SupplierQuote $record): bool => ! $record instanceof \App\Models\SupplierQuote || $this->isRecordSupplierTaxable($record)),
                                Placeholder::make('total_display')
                                    ->label('Total')
                                    ->content(fn (?SupplierQuote $record): string => $record instanceof \App\Models\SupplierQuote
                                        ? ($record->currency?->format((float) $record->total) ?? number_format((float) $record->total, 2))
                                        : '0,-'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('subtotal_base_display')
                                    ->label('Subtotal (Base)')
                                    ->content(function (?SupplierQuote $record): string {
                                        if (! $record instanceof \App\Models\SupplierQuote) {
                                            return '0,-';
                                        }
                                        /** @var \App\Models\Team|null $team */
                                        $team = Filament::getTenant();
                                        $baseCurrency = $team?->getBaseCurrency();

                                        return $baseCurrency !== null
                                            ? $baseCurrency->formatNumber((float) $record->subtotal_base)
                                            : number_format((float) $record->subtotal_base, 2);
                                    }),
                                Placeholder::make('tax_total_base_display')
                                    ->label('Tax Total (Base)')
                                    ->content(function (?SupplierQuote $record): string {
                                        if (! $record instanceof \App\Models\SupplierQuote) {
                                            return '0,-';
                                        }
                                        /** @var \App\Models\Team|null $team */
                                        $team = Filament::getTenant();
                                        $baseCurrency = $team?->getBaseCurrency();

                                        return $baseCurrency !== null
                                            ? $baseCurrency->formatNumber((float) $record->tax_total_base)
                                            : number_format((float) $record->tax_total_base, 2);
                                    })
                                    ->visible(fn (?SupplierQuote $record): bool => ! $record instanceof \App\Models\SupplierQuote || $this->isRecordSupplierTaxable($record)),
                                Placeholder::make('total_base_display')
                                    ->label('Total (Base)')
                                    ->content(function (?SupplierQuote $record): string {
                                        if (! $record instanceof \App\Models\SupplierQuote) {
                                            return '0,-';
                                        }
                                        /** @var \App\Models\Team|null $team */
                                        $team = Filament::getTenant();
                                        $baseCurrency = $team?->getBaseCurrency();

                                        return $baseCurrency !== null
                                            ? $baseCurrency->format((float) $record->total_base)
                                            : number_format((float) $record->total_base, 2);
                                    }),
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
            ->selectable()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('quote_number')
                    ->label('Quote #')
                    
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    
                    ->sortable(),
                TextColumn::make('supplier_reference')
                    ->label('Supplier Ref')
                    
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('currency.code')
                    ->label('Currency')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (SupplierQuote $record): string => $record->currency !== null ? $record->currency->format((float) $record->total) : number_format((float) $record->total, 2))
                    ->sortable(),
                TextColumn::make('total_base')
                    ->label('Total (Base)')
                    ->formatStateUsing(function (SupplierQuote $record): string {
                        /** @var \App\Models\Team|null $team */
                        $team = Filament::getTenant();
                        $baseCurrency = $team?->getBaseCurrency();

                        return $baseCurrency !== null ? $baseCurrency->format((float) $record->total_base) : number_format((float) $record->total_base, 2);
                    })
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
                Action::make('compareQuotes')
                    ->label('Compare Quotes')
                    ->icon('heroicon-o-scale')
                    ->color('info')
                    ->modalHeading('Compare Supplier Quotes')
                    ->modalWidth('7xl')
                    ->modalContent(fn (): View => view('filament.modals.supplier-quote-comparison', [
                        'request' => $this->getOwnerRecord(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(function (): bool {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();

                        return $request->supplierQuotes()
                            ->whereIn('status', [SupplierQuoteStatus::RECEIVED, SupplierQuoteStatus::SELECTED])
                            ->count() >= 2;
                    }),
                Action::make('viewQE')
                    ->label('View QE')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->url(function (): ?string {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();
                        $latestQE = $request->quotationEvaluations()->latest()->first();

                        return $latestQE !== null
                            ? QuotationEvaluationResource::getUrl('view', ['record' => $latestQE])
                            : null;
                    })
                    ->openUrlInNewTab()
                    ->visible(function (): bool {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();

                        return $request->quotationEvaluations()->exists();
                    }),
                Action::make('createQE')
                    ->label('Create QE')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->modalHeading('Create Quotation Evaluation')
                    ->modalDescription('Generate an internal QE document from this quote comparison')
                    ->modalWidth('xl')
                    ->modalContent(fn (): View => view('filament.modals.create-quotation-evaluation', [
                        'request' => $this->getOwnerRecord(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(function (): bool {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();

                        // Only show Create QE if no QE exists
                        if ($request->quotationEvaluations()->exists()) {
                            return false;
                        }

                        // Check if there are supplier quotes with prices entered
                        // Similar to Compare Quotes button - need quotes with RECEIVED or SELECTED status
                        $quotesWithPrices = $request->supplierQuotes()
                            ->whereIn('status', [SupplierQuoteStatus::RECEIVED, SupplierQuoteStatus::SELECTED])
                            ->whereHas('items', function ($query): void {
                                $query->where('unit_price', '>', 0);
                            })
                            ->exists();

                        return $quotesWithPrices;
                    })
                    ->disabled(function (): bool {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();

                        return ! $request->supplierQuotes()
                            ->where('status', SupplierQuoteStatus::SELECTED)
                            ->exists();
                    })
                    ->tooltip(function (): ?string {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();

                        $hasSelected = $request->supplierQuotes()
                            ->where('status', SupplierQuoteStatus::SELECTED)
                            ->exists();

                        return $hasSelected ? null : 'Please apply selected supplier quotes first';
                    }),
            ])
            ->recordAction('edit')
            ->recordActions([
                EditAction::make()
                    ->label('Input Supplier Price')
                    ->icon('heroicon-o-pencil-square')
                    ->size(Size::Small)
                    ->mutateFormDataUsing(function (array $data, SupplierQuote $record): array {
                        // Check if items in form data have prices
                        $hasPrices = false;
                        if (isset($data['items']) && is_array($data['items'])) {
                            foreach ($data['items'] as $item) {
                                $unitPrice = (float) ($item['unit_price'] ?? 0);
                                if ($unitPrice > 0) {
                                    $hasPrices = true;
                                    break;
                                }
                            }
                        }
                        
                        // Also check existing items if form items don't have prices yet
                        if (! $hasPrices) {
                            $hasPrices = $record->items()->where('unit_price', '>', 0)->exists();
                        }
                        
                        // Update status from PENDING to RECEIVED if prices exist
                        if ($hasPrices && ($data['status'] ?? null) === SupplierQuoteStatus::PENDING->value) {
                            $data['status'] = SupplierQuoteStatus::RECEIVED->value;
                        }
                        
                        return $data;
                    })
                    ->after(function (SupplierQuote $record): void {
                        // After save, check if status needs to be updated
                        // Reload to get fresh data including items
                        $record->refresh();
                        
                        // Check if items have prices
                        $hasPrices = $record->items()->where('unit_price', '>', 0)->exists();
                        $total = (float) $record->total;
                        
                        // Update status from PENDING to RECEIVED if prices exist
                        if ($record->status === SupplierQuoteStatus::PENDING && ($hasPrices || $total > 0)) {
                            $record->status = SupplierQuoteStatus::RECEIVED;
                            $record->save();
                        }
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

        // Check if supplier is taxable
        $isTaxable = $this->isSupplierTaxable($get);

        // If not taxable, set tax values to 0 and clear tax fields
        if (! $isTaxable) {
            $set('tax_rate', 0);
            $set('tax_code_id', null);
            $set('is_tax_inclusive', false);
        }

        $taxRate = $isTaxable ? (float) ($get('tax_rate') ?? 0) : 0;
        $isTaxInclusive = $isTaxable && (bool) $get('is_tax_inclusive');

        $lineAmount = $quantity * $unitPrice;

        if ($isTaxInclusive && $taxRate > 0) {
            $lineTotal = $lineAmount;
            $lineSubtotal = $lineAmount / (1 + $taxRate / 100);
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

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Request $ownerRecord */
        $hasSelected = $ownerRecord->supplierQuotes()
            ->where('status', SupplierQuoteStatus::SELECTED)
            ->exists();

        return $hasSelected ? '✓' : null;
    }

    /**
     * Check if the supplier selected in the form is taxable.
     */
    private function isSupplierTaxable(Get $get): bool
    {
        // Get supplier_id from the parent form (go up from repeater item to form level)
        $supplierId = $get('../../supplier_id');

        if ($supplierId === null) {
            // When editing existing quote, check the record's supplier
            $record = $this->getMountedTableActionRecord() ?? $this->getRecord();
            if ($record instanceof SupplierQuote && $record->supplier_id !== null) {
                $supplierId = $record->supplier_id;
            } else {
                return true; // Default to showing tax fields if no supplier selected
            }
        }

        /** @var Company|null $supplier */
        $supplier = Company::query()->find($supplierId);

        return $supplier?->is_taxable ?? true;
    }

    /**
     * Check if the supplier in an existing record is taxable.
     */
    private function isRecordSupplierTaxable(?SupplierQuote $record): bool
    {
        if (! $record instanceof \App\Models\SupplierQuote || $record->supplier_id === null) {
            return true; // Default to showing tax fields
        }

        /** @var Company|null $supplier */
        $supplier = Company::query()->find($record->supplier_id);

        return $supplier?->is_taxable ?? true;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Request $ownerRecord */
        $hasSelected = $ownerRecord->supplierQuotes()
            ->where('status', SupplierQuoteStatus::SELECTED)
            ->exists();

        return $hasSelected ? 'success' : null;
    }
}
