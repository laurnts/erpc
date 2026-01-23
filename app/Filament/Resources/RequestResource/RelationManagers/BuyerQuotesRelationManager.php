<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Actions\KeyAccount\CreateKeyAccount;
use App\Enums\BuyerQuoteStatus;
use App\Enums\PrepaymentType;
use App\Enums\RequestStage;
use App\Filament\Actions\DownloadPdfAction;
use App\Filament\Resources\KeyAccountResource;
use App\Filament\Resources\ProfitAndLossResource;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\BuyerQuote;
use App\Models\Currency;
use App\Models\KeyAccount;
use App\Models\ProfitAndLoss;
use App\Models\Request;
use App\Models\TaxCode;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
use Illuminate\Database\Eloquent\Model;

final class BuyerQuotesRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'buyerQuotes';

    protected static ?string $title = 'Buyer Quotes';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-text';

    protected static function getAssociatedStage(): RequestStage
    {
        return RequestStage::PREPARING_BUYER_QUOTE;
    }

    protected static function getBaseTabTitle(): string
    {
        return 'Buyer Quotes';
    }

    /**
     * Get the form schema for buyer quotes.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public function getFormSchema(): array
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return [
            Section::make('Quote Details')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Placeholder::make('quote_number_display')
                                ->label('Quote Number')
                                ->content(fn (?BuyerQuote $record): string => $record->quote_number ?? 'Auto-generated'),
                            Placeholder::make('version_display')
                                ->label('Version')
                                ->content(fn (?BuyerQuote $record): string => $record instanceof \App\Models\BuyerQuote ? 'v'.$record->version : 'v1'),
                            Select::make('status')
                                ->options(BuyerQuoteStatus::class)
                                ->default(BuyerQuoteStatus::DRAFT)
                                ->required()
                                ->disabled(fn (?BuyerQuote $record): bool => $record instanceof \App\Models\BuyerQuote && ! $record->status->canEdit()),
                        ]),
                    Grid::make(2)
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
                                ->searchable()
                                ->required(),
                            DatePicker::make('valid_until')
                                ->label('Valid Until')
                                ->default(function (): \Illuminate\Support\Carbon {
                                    /** @var \App\Models\Team|null $team */
                                    $team = Filament::getTenant();
                                    $validityDays = $team?->getErpSettings()->quote_validity_days ?? 30;

                                    return now()->addDays($validityDays);
                                }),
                        ]),
                    Hidden::make('exchange_rate')->default(1),
                ]),

            Section::make('Payment Terms')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('prepayment_type')
                                ->label('Prepayment Type')
                                ->options(PrepaymentType::class)
                                ->default(PrepaymentType::PERCENT)
                                ->live()
                                ->afterStateUpdated(fn (Set $set): mixed => $set('prepayment_amount', 0)),
                            TextInput::make('prepayment_amount')
                                ->label('Prepayment')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(fn (Get $get): ?int => $get('prepayment_type') === PrepaymentType::PERCENT->value ? 100 : null)
                                ->suffix(fn (Get $get): string => $get('prepayment_type') === PrepaymentType::PERCENT->value ? '%' : ''),
                        ]),
                    Repeater::make('paymentTerms')
                        ->relationship()
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('due_days')
                                        ->label('Due Days')
                                        ->numeric()
                                        ->required()
                                        ->default(0)
                                        ->minValue(0)
                                        ->suffix('days'),
                                    TextInput::make('percentage')
                                        ->label('Percentage')
                                        ->numeric()
                                        ->required()
                                        ->default(0)
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->suffix('%'),
                                ]),
                        ])
                        ->defaultItems(1)
                        ->itemLabel(fn (array $state): ?string => isset($state['due_days'], $state['percentage']) ? "{$state['due_days']} days - {$state['percentage']}%" : null)
                        ->addActionLabel('Add Payment Terms')
                        ->reorderableWithButtons()
                        ->collapsible(),
                ])
                ->collapsible(),

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
                                            $requestItem = $request->items()->with('article.defaultTaxCode')->find($state);
                                            if ($requestItem !== null) {
                                                $set('article_id', $requestItem->article_id);
                                                $set('description', $requestItem->description);
                                                $set('quantity', $requestItem->quantity);
                                                $set('unit', $requestItem->unit);

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
                                        ->default(1)
                                        ->step(0.0001)
                                        ->columnSpan(2)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                    TextInput::make('unit')
                                        ->default('pcs')
                                        ->columnSpan(2),
                                ]),
                            Grid::make(12)
                                ->schema([
                                    Hidden::make('from_supplier')
                                        ->dehydrated(false),
                                    Hidden::make('supplier_quote_item_id'),
                                    Hidden::make('margin_amount'),
                                    Hidden::make('margin_percent'),
                                    TextInput::make('cost_price')
                                        ->label('Cost Price')
                                        ->numeric()
                                        ->default(0)
                                        ->step(0.0001)
                                        ->columnSpan(2)
                                        ->helperText(function (Get $get): string {
                                            // First check the form state
                                            $fromSupplier = $get('from_supplier');
                                            if ($fromSupplier !== null) {
                                                return "From: {$fromSupplier}";
                                            }

                                            // Then check if we can get it from the supplier_quote_item_id
                                            $supplierQuoteItemId = $get('supplier_quote_item_id');
                                            if ($supplierQuoteItemId !== null) {
                                                $supplierQuoteItem = \App\Models\SupplierQuoteItem::with('supplierQuote.supplier')->find($supplierQuoteItemId);
                                                if ($supplierQuoteItem?->supplierQuote?->supplier) {
                                                    return "From: {$supplierQuoteItem->supplierQuote->supplier->name}";
                                                }
                                            }

                                            return 'No supplier quote';
                                        })
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Set $set, Get $get): void {
                                            $costPrice = (float) ($get('cost_price') ?? 0);
                                            $unitPrice = (float) ($get('unit_price') ?? 0);

                                            if ($costPrice > 0) {
                                                $marginPercent = (($unitPrice - $costPrice) / $costPrice) * 100;
                                                $set('margin_percent_input', round($marginPercent, 2));
                                            }

                                            $this->calculateItemTotals($set, $get);
                                        }),
                                    TextInput::make('unit_price')
                                        ->label('Selling Price (Net)')
                                        ->numeric()
                                        ->required()
                                        ->default(0)
                                        ->step(0.0001)
                                        ->columnSpan(2)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Set $set, Get $get): void {
                                            $costPrice = (float) ($get('cost_price') ?? 0);
                                            $unitPrice = (float) ($get('unit_price') ?? 0);

                                            if ($costPrice > 0) {
                                                $marginPercent = (($unitPrice - $costPrice) / $costPrice) * 100;
                                                $set('margin_percent_input', round($marginPercent, 2));
                                            }

                                            $this->calculateItemTotals($set, $get);
                                        }),
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
                                        ->searchable()
                                        ->columnSpan(2)
                                        ->live()
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
                                        ->label('+ Tax')
                                        ->inline(false)
                                        ->columnSpan(1)
                                        ->live()
                                        ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                    TextInput::make('tax_rate')
                                        ->label('Tax %')
                                        ->numeric()
                                        ->default(0)
                                        ->step(0.01)
                                        ->columnSpan(1)
                                        ->disabled()
                                        ->dehydrated(),
                                    TextInput::make('margin_percent_input')
                                        ->label('Margin %')
                                        ->numeric()
                                        ->default(function (): float {
                                            /** @var \App\Models\Team|null $team */
                                            $team = Filament::getTenant();

                                            return $team?->getErpSettings()->default_margin_percent ?? 3.0;
                                        })
                                        ->step(0.01)
                                        ->suffix('%')
                                        ->columnSpan(2)
                                        ->live(onBlur: true)
                                        ->afterStateHydrated(function (Set $set, Get $get): void {
                                            // Populate from stored margin_percent when editing
                                            $marginPercent = $get('margin_percent');
                                            if ($marginPercent !== null) {
                                                $set('margin_percent_input', round((float) $marginPercent, 2));
                                            }
                                        })
                                        ->afterStateUpdated(function (Set $set, Get $get, ?float $state): void {
                                            $marginPercent = $state ?? 0;
                                            $costPrice = (float) ($get('cost_price') ?? 0);

                                            if ($costPrice > 0) {
                                                $unitPrice = round($costPrice * (1 + $marginPercent / 100), 4);
                                                $set('unit_price', $unitPrice);
                                            }

                                            $this->calculateItemTotals($set, $get);
                                        })
                                        ->dehydrated(false),
                                    TextInput::make('line_total')
                                        ->label('Line Total')
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(2),
                                ]),
                            Checkbox::make('hide_from_pdf')
                                ->label('Hide from PDF')
                                ->helperText('This item will not appear in the PDF and its price will be distributed to visible items')
                                ->columnSpanFull(),
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
                        ->live()
                        ->deletable(fn (?BuyerQuote $record): bool => ! $record instanceof \App\Models\BuyerQuote || $record->status->canEdit())
                        ->addable(fn (?BuyerQuote $record): bool => ! $record instanceof \App\Models\BuyerQuote || $record->status->canEdit())
                        ->itemLabel(fn (array $state): ?string => $state['description'] ?? null),
                ]),

            Section::make('Summary')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            Placeholder::make('subtotal_display')
                                ->label('Subtotal')
                                ->live()
                                ->content(function (Get $get): string {
                                    /** @var array<int, array<string, mixed>> $items */
                                    $items = $get('items') ?? [];
                                    $subtotal = 0.0;
                                    foreach ($items as $item) {
                                        $subtotal += (float) ($item['line_subtotal'] ?? 0);
                                    }

                                    $currencyId = $get('currency_id');
                                    /** @var Currency|null $currency */
                                    $currency = $currencyId !== null ? Currency::find($currencyId) : null;

                                    return $currency instanceof Currency ? $currency->formatNumber($subtotal) : number_format($subtotal, 2);
                                }),
                            Placeholder::make('tax_total_display')
                                ->label('Tax Total')
                                ->live()
                                ->content(function (Get $get): string {
                                    /** @var array<int, array<string, mixed>> $items */
                                    $items = $get('items') ?? [];
                                    $taxTotal = 0.0;
                                    foreach ($items as $item) {
                                        $taxTotal += (float) ($item['line_tax'] ?? 0);
                                    }

                                    $currencyId = $get('currency_id');
                                    /** @var Currency|null $currency */
                                    $currency = $currencyId !== null ? Currency::find($currencyId) : null;

                                    return $currency instanceof Currency ? $currency->formatNumber($taxTotal) : number_format($taxTotal, 2);
                                }),
                            Placeholder::make('total_display')
                                ->label('Total')
                                ->live()
                                ->content(function (Get $get): string {
                                    /** @var array<int, array<string, mixed>> $items */
                                    $items = $get('items') ?? [];
                                    $total = 0.0;
                                    foreach ($items as $item) {
                                        $total += (float) ($item['line_total'] ?? 0);
                                    }

                                    $currencyId = $get('currency_id');
                                    /** @var Currency|null $currency */
                                    $currency = $currencyId !== null ? Currency::find($currencyId) : null;

                                    return $currency instanceof Currency ? $currency->format($total) : number_format($total, 2);
                                }),
                            Placeholder::make('margin_display')
                                ->label('Total Margin')
                                ->live()
                                ->content(function (Get $get): string {
                                    /** @var array<int, array<string, mixed>> $items */
                                    $items = $get('items') ?? [];
                                    $totalMargin = 0.0;
                                    $totalCost = 0.0;

                                    foreach ($items as $item) {
                                        $qty = (float) ($item['quantity'] ?? 0);
                                        $marginAmount = (float) ($item['margin_amount'] ?? 0);
                                        $costPrice = (float) ($item['cost_price'] ?? 0);
                                        $totalMargin += $marginAmount * $qty;
                                        $totalCost += $costPrice * $qty;
                                    }

                                    $marginPercent = $totalCost > 0 ? ($totalMargin / $totalCost) * 100 : 0;

                                    $currencyId = $get('currency_id');
                                    /** @var Currency|null $currency */
                                    $currency = $currencyId !== null ? Currency::find($currencyId) : null;
                                    $formattedMargin = $currency instanceof Currency ? $currency->formatNumber($totalMargin) : number_format($totalMargin, 2);

                                    return sprintf('%s (%.1f%%)', $formattedMargin, $marginPercent);
                                }),
                        ]),
                ])
                ->collapsible(),

            Section::make('Notes')
                ->schema([
                    Textarea::make('notes')
                        ->label('Notes (Visible to Buyer)')
                        ->rows(2),
                    Textarea::make('internal_notes')
                        ->label('Internal Notes')
                        ->rows(2)
                        ->helperText('Not visible on buyer PDF'),
                    Textarea::make('terms_and_conditions')
                        ->label('Terms & Conditions')
                        ->rows(3),
                ])
                ->collapsed(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components($this->getFormSchema());
    }

    public function table(Table $table): Table
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('quote_number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('quote_number')
                    ->label('Quote #')
                    ->searchable()
                    ->sortable()
                    ->description(fn (BuyerQuote $record): string => 'v'.$record->version),
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('currency.code')
                    ->label('Currency')
                    ->sortable(),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->formatStateUsing(fn (BuyerQuote $record): string => $record->currency !== null ? $record->currency->formatNumber((float) $record->subtotal) : number_format((float) $record->subtotal, 2))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (BuyerQuote $record): string => $record->currency !== null ? $record->currency->format((float) $record->total) : number_format((float) $record->total, 2))
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date()
                    ->sortable()
                    ->color(fn (BuyerQuote $record): string => $record->is_expired ? 'danger' : 'success'),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
                TextColumn::make('version')
                    ->label('Ver')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BuyerQuoteStatus::class),
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
                    ->size(Size::Small)
                    ->modalWidth('7xl')
                    ->fillForm(function () use ($request): array {
                        /** @var \App\Models\Team|null $team */
                        $team = Filament::getTenant();
                        $settings = $team?->getErpSettings();

                        // Get default currency
                        $defaultCurrencyCode = $settings->default_currency ?? 'USD';
                        $currencyId = Currency::query()
                            ->where('code', $defaultCurrencyCode)
                            ->where('is_active', true)
                            ->value('id');

                        // Get default tax code
                        $defaultTaxCode = TaxCode::query()
                            ->where('team_id', $request->team_id)
                            ->where('is_default', true)
                            ->where('is_active', true)
                            ->first();

                        // Get default margin percentage
                        $defaultMarginPercent = $settings->default_margin_percent ?? 3.0;

                        // Pre-populate items from SELECTED supplier quote items only
                        $items = [];
                        $sortOrder = 0;

                        // Get all selected supplier quote items for this request (is_selected = true)
                        $selectedSupplierQuoteItems = \App\Models\SupplierQuoteItem::query()
                            ->whereHas('supplierQuote', fn ($q) => $q->where('request_id', $request->getKey()))
                            ->where('is_selected', true)
                            ->with(['supplierQuote.supplier', 'requestItem.article'])
                            ->get();

                        foreach ($selectedSupplierQuoteItems as $supplierQuoteItem) {
                            $requestItem = $supplierQuoteItem->requestItem;
                            if ($requestItem === null) {
                                continue;
                            }

                            $costPrice = (float) $supplierQuoteItem->unit_price_exc_tax;
                            $supplierQuoteItemId = $supplierQuoteItem->getKey();
                            $supplierName = $supplierQuoteItem->supplierQuote->supplier->name ?? null;

                            // Calculate selling price with default margin
                            $unitPrice = $costPrice > 0
                                ? round($costPrice * (1 + $defaultMarginPercent / 100), 4)
                                : 0.0;

                            // Calculate tax-related values
                            $taxRate = $defaultTaxCode !== null ? (float) $defaultTaxCode->rate : 0.0;
                            $addTax = $defaultTaxCode !== null && $defaultTaxCode->is_inclusive_default;
                            $quantity = (float) $requestItem->quantity;

                            // Unit price is always the NET price
                            $unitPriceExcTax = $unitPrice;

                            // Line subtotal is quantity * net price (amount before tax)
                            $lineSubtotal = $quantity * $unitPrice;

                            // Calculate tax if tax rate > 0 (tax should always be calculated when tax code is selected)
                            if ($taxRate > 0) {
                                $lineTax = $lineSubtotal * $taxRate / 100;
                                $lineTotal = $lineSubtotal + $lineTax;
                            } else {
                                $lineTax = 0;
                                $lineTotal = $lineSubtotal;
                            }

                            // Calculate margin (based on net prices)
                            $marginAmount = $unitPriceExcTax - $costPrice;
                            $marginPercent = $costPrice > 0 ? ($marginAmount / $costPrice) * 100 : 0;

                            $items[] = [
                                'request_item_id' => $requestItem->getKey(),
                                'article_id' => $requestItem->article_id,
                                'supplier_quote_item_id' => $supplierQuoteItemId,
                                'from_supplier' => $supplierName,
                                'description' => $requestItem->article !== null ? $requestItem->article->name : $requestItem->description,
                                'quantity' => (string) $requestItem->quantity,
                                'unit' => $requestItem->unit,
                                'cost_price' => (string) $costPrice,
                                'unit_price' => (string) $unitPrice,
                                'unit_price_exc_tax' => (string) round($unitPriceExcTax, 4),
                                'tax_code_id' => $defaultTaxCode?->getKey(),
                                'tax_rate' => (string) $taxRate,
                                'tax_amount' => (string) round($lineTax / max($quantity, 0.0001), 4),
                                'is_tax_inclusive' => $addTax,
                                'line_subtotal' => (string) round($lineSubtotal, 4),
                                'line_tax' => (string) round($lineTax, 4),
                                'line_total' => (string) round($lineTotal, 4),
                                'margin_amount' => (string) round($marginAmount, 4),
                                'margin_percent' => (string) round($marginPercent, 4),
                                'margin_percent_input' => (string) round($marginPercent, 2),
                                'sort_order' => $sortOrder++,
                            ];
                        }

                        $defaultPaymentTermsDays = $settings->default_payment_terms_days ?? 30;

                        return [
                            'status' => BuyerQuoteStatus::DRAFT,
                            'currency_id' => $currencyId,
                            'exchange_rate' => 1,
                            'valid_until' => now()->addDays($settings->quote_validity_days ?? 30),
                            'prepayment_type' => PrepaymentType::PERCENT->value,
                            'prepayment_amount' => 0,
                            'paymentTerms' => [
                                [
                                    'due_days' => $defaultPaymentTermsDays,
                                    'percentage' => 100,
                                    'sort_order' => 0,
                                ],
                            ],
                            'items' => $items,
                        ];
                    })
                    ->mutateFormDataUsing(function (array $data) use ($request): array {
                        $data['request_id'] = $request->getKey();
                        $data['buyer_id'] = $request->buyer_id;

                        return $data;
                    })
                    ->after(function (BuyerQuote $record) use ($request): void {
                        // If items weren't created by the Repeater, create them manually
                        if ($record->items()->count() === 0) {
                            /** @var \App\Models\Team|null $team */
                            $team = Filament::getTenant();
                            $settings = $team?->getErpSettings();
                            $defaultMarginPercent = $settings->default_margin_percent ?? 3.0;

                            // Get default tax code
                            $defaultTaxCode = TaxCode::query()
                                ->where('team_id', $request->team_id)
                                ->where('is_default', true)
                                ->where('is_active', true)
                                ->first();

                            $sortOrder = 0;

                            // Get all selected supplier quote items for this request (is_selected = true)
                            $selectedSupplierQuoteItems = \App\Models\SupplierQuoteItem::query()
                                ->whereHas('supplierQuote', fn ($q) => $q->where('request_id', $request->getKey()))
                                ->where('is_selected', true)
                                ->with(['requestItem.article'])
                                ->get();

                            foreach ($selectedSupplierQuoteItems as $supplierQuoteItem) {
                                $requestItem = $supplierQuoteItem->requestItem;
                                if ($requestItem === null) {
                                    continue;
                                }

                                $costPrice = (float) $supplierQuoteItem->unit_price_exc_tax;
                                $supplierQuoteItemId = $supplierQuoteItem->getKey();

                                // Calculate selling price with default margin
                                $unitPrice = $costPrice > 0
                                    ? round($costPrice * (1 + $defaultMarginPercent / 100), 4)
                                    : 0.0;

                                // Calculate tax-related values
                                $taxRate = $defaultTaxCode !== null ? (float) $defaultTaxCode->rate : 0.0;
                                $addTax = $defaultTaxCode !== null && $defaultTaxCode->is_inclusive_default;
                                $quantity = (float) $requestItem->quantity;

                                // Unit price is always the NET price
                                $unitPriceExcTax = $unitPrice;

                                // Line subtotal is quantity * net price (amount before tax)
                                $lineSubtotal = $quantity * $unitPrice;

                                // Calculate tax if tax rate > 0 (tax should always be calculated when tax code is selected)
                                if ($taxRate > 0) {
                                    $lineTax = $lineSubtotal * $taxRate / 100;
                                    $lineTotal = $lineSubtotal + $lineTax;
                                } else {
                                    $lineTax = 0;
                                    $lineTotal = $lineSubtotal;
                                }

                                // Calculate margin (based on net prices)
                                $marginAmount = $unitPriceExcTax - $costPrice;
                                $marginPercent = $costPrice > 0 ? ($marginAmount / $costPrice) * 100 : 0;

                                $record->items()->create([
                                    'request_item_id' => $requestItem->getKey(),
                                    'article_id' => $requestItem->article_id,
                                    'supplier_quote_item_id' => $supplierQuoteItemId,
                                    'description' => $requestItem->article !== null ? $requestItem->article->name : $requestItem->description,
                                    'quantity' => $requestItem->quantity,
                                    'unit' => $requestItem->unit,
                                    'cost_price' => $costPrice,
                                    'unit_price' => $unitPrice,
                                    'unit_price_exc_tax' => round($unitPriceExcTax, 4),
                                    'tax_code_id' => $defaultTaxCode?->getKey(),
                                    'tax_rate' => $taxRate,
                                    'tax_amount' => round($lineTax / max($quantity, 0.0001), 4),
                                    'is_tax_inclusive' => $addTax,
                                    'line_subtotal' => round($lineSubtotal, 4),
                                    'line_tax' => round($lineTax, 4),
                                    'line_total' => round($lineTotal, 4),
                                    'margin_amount' => round($marginAmount, 4),
                                    'margin_percent' => round($marginPercent, 4),
                                    'sort_order' => $sortOrder++,
                                ]);
                            }
                        }
                    }),
                Action::make('createPnl')
                    ->label('Create PNL')
                    ->icon('heroicon-o-chart-bar')
                    ->size(Size::Small)
                    ->color('success')
                    ->visible(fn () => $request->buyerQuotes()->exists())
                    ->modalWidth('xl')
                    ->form([
                        Section::make('PNL Information')
                            ->schema([
                                Placeholder::make('pnl_number_placeholder')
                                    ->label('PNL Number')
                                    ->content('Auto-generated after save'),
                                DatePicker::make('pnl_date')
                                    ->label('Date')
                                    ->required()
                                    ->default(now()),
                                TextInput::make('request_number')
                                    ->label('Request')
                                    ->default($request->request_number)
                                    ->disabled()
                                    ->dehydrated(false),
                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3),
                        Section::make('Central Purchasing')
                            ->description('Approval workflow personnel')
                            ->schema([
                                Select::make('prepared_by_id')
                                    ->label('Prepared By')
                                    ->options(fn (): array => KeyAccount::query()
                                        ->where('team_id', Filament::getTenant()?->getKey())
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn (KeyAccount $ka): array => [$ka->getKey() => $ka->display_name])
                                        ->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm(KeyAccountResource::getFormSchema())
                                    ->createOptionUsing(function (array $data): int {
                                        $keyAccount = app(CreateKeyAccount::class)->execute($data);

                                        return $keyAccount->id;
                                    }),
                                TextInput::make('dept_head_sales_name')
                                    ->label('Dept Head of Sales')
                                    ->maxLength(255),
                                TextInput::make('deputy_director_name')
                                    ->label('Deputy Director')
                                    ->maxLength(255),
                                TextInput::make('approved_by_name')
                                    ->label('Approved By')
                                    ->maxLength(255),
                            ])
                            ->columns(2),
                    ])
                    ->action(function (array $data) use ($request): void {
                        // Find the latest valid buyer quote (not rejected/superseded)
                        $buyerQuote = $request->buyerQuotes()
                            ->whereNotIn('status', [BuyerQuoteStatus::REJECTED, BuyerQuoteStatus::SUPERSEDED])
                            ->latest()
                            ->first();

                        // Create PNL (team_id, creator_id, and pnl_number are auto-set by observer)
                        $pnl = ProfitAndLoss::create([
                            'request_id' => $request->getKey(),
                            'buyer_quote_id' => $buyerQuote?->getKey(),
                            'description' => $data['description'] ?? null,
                            'pnl_date' => $data['pnl_date'],
                            'prepared_by_id' => $data['prepared_by_id'] ?? null,
                            'dept_head_sales_name' => $data['dept_head_sales_name'] ?? null,
                            'deputy_director_name' => $data['deputy_director_name'] ?? null,
                            'approved_by_name' => $data['approved_by_name'] ?? null,
                        ]);

                        Notification::make()
                            ->title('PNL created')
                            ->body("PNL {$pnl->pnl_number} has been created successfully.")
                            ->success()
                            ->send();

                        redirect(ProfitAndLossResource::getUrl('view', ['record' => $pnl]));
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->visible(fn (BuyerQuote $record): bool => ! $record->status->canEdit())
                    ->modalWidth('7xl')
                    ->form(fn (): array => $this->getFormSchema()),
                EditAction::make()
                    ->visible(fn (BuyerQuote $record): bool => $record->status->canEdit())
                    ->modalWidth('7xl')
                    ->form(fn (): array => $this->getFormSchema()),
                \Filament\Actions\DeleteAction::make()
                    ->visible(fn (BuyerQuote $record): bool => $record->status->canEdit()),
                DownloadPdfAction::make()
                    ->label('PDF'),
                Action::make('send')
                    ->label('Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (BuyerQuote $record): bool => $record->status->canSend())
                    ->requiresConfirmation()
                    ->modalHeading('Send this quote?')
                    ->modalDescription('This will mark the quote as sent and set the issue date to today.')
                    ->action(function (BuyerQuote $record): void {
                        $record->markAsSent();
                        Notification::make()
                            ->title('Quote sent')
                            ->success()
                            ->send();
                    }),
                Action::make('accept')
                    ->label('Accept')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BuyerQuote $record): bool => $record->status === BuyerQuoteStatus::SENT)
                    ->requiresConfirmation()
                    ->modalHeading('Accept this quote?')
                    ->modalDescription('This will mark the quote as accepted by the buyer.')
                    ->action(function (BuyerQuote $record): void {
                        $record->markAsAccepted();
                        Notification::make()
                            ->title('Quote accepted')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (BuyerQuote $record): bool => $record->status === BuyerQuoteStatus::SENT)
                    ->requiresConfirmation()
                    ->modalHeading('Reject this quote?')
                    ->modalDescription('This will mark the quote as rejected by the buyer.')
                    ->action(function (BuyerQuote $record): void {
                        $record->markAsRejected();
                        Notification::make()
                            ->title('Quote rejected')
                            ->warning()
                            ->send();
                    }),
                Action::make('newVersion')
                    ->label('New Version')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('primary')
                    ->visible(fn (BuyerQuote $record): bool => ! $record->status->canEdit())
                    ->requiresConfirmation()
                    ->modalHeading('Create new version?')
                    ->modalDescription('This will create a new draft version of this quote and mark the current one as superseded.')
                    ->action(function (BuyerQuote $record): void {
                        $newQuote = $record->createNewVersion();
                        Notification::make()
                            ->title('New version created')
                            ->body("Version {$newQuote->version} has been created as a draft.")
                            ->success()
                            ->send();
                    }),
                Action::make('extend')
                    ->label('Extend')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->visible(fn (BuyerQuote $record): bool => $record->status->isActive())
                    ->form([
                        DatePicker::make('new_valid_until')
                            ->label('New Valid Until Date')
                            ->required()
                            ->minDate(fn (BuyerQuote $record) => $record->valid_until ?? now())
                            ->default(fn (BuyerQuote $record) => $record->valid_until?->addDays(14)),
                        Textarea::make('reason')
                            ->label('Reason for Extension')
                            ->rows(2)
                            ->placeholder('Optional: Explain why the validity is being extended'),
                    ])
                    ->action(function (BuyerQuote $record, array $data): void {
                        $record->extendValidity(
                            \Illuminate\Support\Carbon::parse($data['new_valid_until']),
                            $data['reason'] ?? null
                        );
                        Notification::make()
                            ->title('Quote validity extended')
                            ->success()
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
     *
     * Selling Price (unit_price) is always the NET/exclusive price.
     * When "Add Tax" is checked, tax is added to get the line total.
     */
    private function calculateItemTotals(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0); // Always NET price
        $costPrice = (float) ($get('cost_price') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);
        $get('is_tax_inclusive'); // When checked, add tax to total

        // Unit price is always the net price
        $unitPriceExcTax = $unitPrice;

        // Line subtotal is quantity * net price (amount before tax)
        $lineSubtotal = $quantity * $unitPrice;

        // Calculate tax if tax rate > 0 (tax should always be calculated when tax code is selected)
        // The "+ Tax" checkbox indicates tax should be added on top of the subtotal
        if ($taxRate > 0) {
            $lineTax = $lineSubtotal * $taxRate / 100;
            $lineTotal = $lineSubtotal + $lineTax;
        } else {
            $lineTax = 0;
            $lineTotal = $lineSubtotal;
        }

        // Calculate margin (based on net prices)
        $marginAmount = $unitPriceExcTax - $costPrice;
        $marginPercent = $costPrice > 0 ? ($marginAmount / $costPrice) * 100 : 0;

        $set('unit_price_exc_tax', round($unitPriceExcTax, 4));
        $set('line_subtotal', round($lineSubtotal, 4));
        $set('line_tax', round($lineTax, 4));
        $set('line_total', round($lineTotal, 4));
        $set('margin_amount', round($marginAmount, 4));
        $set('margin_percent', round($marginPercent, 4));
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Request $ownerRecord */
        $hasAccepted = $ownerRecord->buyerQuotes()
            ->where('status', BuyerQuoteStatus::ACCEPTED)
            ->exists();

        return $hasAccepted ? '✓' : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Request $ownerRecord */
        $hasAccepted = $ownerRecord->buyerQuotes()
            ->where('status', BuyerQuoteStatus::ACCEPTED)
            ->exists();

        return $hasAccepted ? 'success' : null;
    }
}
