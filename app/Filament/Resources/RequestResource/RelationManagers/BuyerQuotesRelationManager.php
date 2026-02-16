<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\BuyerQuoteStatus;
use App\Enums\CentralPurchasingRole;
use App\Enums\PrepaymentType;
use App\Enums\RequestStage;
use App\Enums\SupplierQuoteStatus;
use App\Filament\Actions\DownloadPdfAction;
use App\Filament\Forms\Components\KeyAccountSelect;
use App\Filament\Resources\ProfitAndLossResource;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\BuyerQuote;
use App\Models\Currency;
use App\Services\TeamMemberService;
use App\Models\ProfitAndLoss;
use App\Models\Request;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
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
use Closure;

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
                                ->selectablePlaceholder(false)
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
                                
                                ->required()
                                ->selectablePlaceholder(false),
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
                                ->selectablePlaceholder(false)
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
                        ->visible(function (): bool {
                            /** @var Request $request */
                            $request = $this->getOwnerRecord();
                            $buyer = $request->buyer;
                            return $buyer?->credit_status ?? true;
                        })
                        ->rules([
                            function (): Closure {
                                return function (string $attribute, $value, Closure $fail) {
                                    /** @var Request $request */
                                    $request = $this->getOwnerRecord();
                                    $buyer = $request->buyer;
                                    
                                    // Only validate if credit_status is enabled
                                    if (!$buyer?->credit_status) {
                                        return;
                                    }
                                    
                                    // Calculate sum of all percentages and count payment terms
                                    $totalPercentage = 0;
                                    $paymentTermCount = 0;
                                    if (is_array($value)) {
                                        foreach ($value as $item) {
                                            $percentage = (float) ($item['percentage'] ?? 0);
                                            if ($percentage > 0) {
                                                $totalPercentage += $percentage;
                                                $paymentTermCount++;
                                            }
                                        }
                                    }
                                    
                                    // Only validate if there are more than 1 payment term
                                    if ($paymentTermCount > 1) {
                                        // Validate sum equals 100
                                        if (abs($totalPercentage - 100) > 0.01) { // Allow small floating point differences
                                            $fail("The total payment terms percentage must equal 100%. Current total: " . number_format($totalPercentage, 2) . "%");
                                        }
                                    }
                                };
                            },
                        ])
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
                                            
                                            // unit_price always represents the net price (Selling Price Net)
                                            $unitPriceExcTax = round($unitPrice, 0);
                                            
                                            // Update unit_price_exc_tax to match
                                            $set('unit_price_exc_tax', $unitPriceExcTax);

                                            if ($costPrice > 0 && $unitPriceExcTax > 0) {
                                                // Margin on selling: ((selling_price - cost_price) / selling_price) * 100
                                                $marginPercent = (($unitPriceExcTax - $costPrice) / $unitPriceExcTax) * 100;
                                                $set('margin_percent_input', (int) round($marginPercent));
                                            }

                                            $this->calculateItemTotals($set, $get);
                                        }),
                                    TextInput::make('unit_price')
                                        ->label('Selling Price (Net)')
                                        ->numeric()
                                        ->required()
                                        ->default(0)
                                        ->step(1)
                                        ->columnSpan(2)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Set $set, Get $get): void {
                                            $costPrice = (float) ($get('cost_price') ?? 0);
                                            $unitPrice = (float) ($get('unit_price') ?? 0);

                                            // unit_price always represents the net price (Selling Price Net)
                                            $unitPriceExcTax = round($unitPrice, 0);
                                            
                                            // Update unit_price_exc_tax to match
                                            $set('unit_price_exc_tax', $unitPriceExcTax);

                                            if ($costPrice > 0 && $unitPriceExcTax > 0) {
                                                // Margin on selling: ((selling_price - cost_price) / selling_price) * 100
                                                $marginPercent = (($unitPriceExcTax - $costPrice) / $unitPriceExcTax) * 100;
                                                $set('margin_percent_input', (int) ceil($marginPercent));
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
                                        
                                        ->selectablePlaceholder(false)
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

                                            return ceil($team?->getErpSettings()->default_margin_percent ?? 3.0);
                                        })
                                        ->step(1)
                                        ->suffix('%')
                                        ->columnSpan(2)
                                        ->live(onBlur: true)
                                        ->afterStateHydrated(function (Set $set, Get $get): void {
                                            // Use unit_price_exc_tax from database if available, otherwise calculate from unit_price
                                            $costPrice = (float) ($get('cost_price') ?? 0);
                                            $unitPrice = (float) ($get('unit_price') ?? 0);
                                            $unitPriceExcTaxStored = (float) ($get('unit_price_exc_tax') ?? 0);
                                            $taxRate = (float) ($get('tax_rate') ?? 0);
                                            $isTaxInclusive = (bool) ($get('is_tax_inclusive') ?? false);
                                            $marginPercentInput = $get('margin_percent_input');
                                            
                                            // unit_price always represents the net price (Selling Price Net)
                                            // unit_price_exc_tax should always equal unit_price (they're both net price)
                                            if ($unitPrice > 0) {
                                                $unitPriceExcTax = round($unitPrice, 0);
                                                // Ensure unit_price_exc_tax matches unit_price
                                                $set('unit_price_exc_tax', $unitPriceExcTax);
                                            } elseif ($unitPriceExcTaxStored > 0) {
                                                // Fallback to stored value if unit_price is not set
                                                $unitPriceExcTax = $unitPriceExcTaxStored;
                                            } else {
                                                $unitPriceExcTax = 0;
                                            }
                                            
                                            // Always recalculate margin from current values to ensure accuracy
                                            // This ensures correct margin even if database has old incorrect values
                                            if ($costPrice > 0 && $unitPriceExcTax > 0) {
                                                // Calculate margin on selling: ((selling_price - cost_price) / selling_price) * 100
                                                $marginPercent = (($unitPriceExcTax - $costPrice) / $unitPriceExcTax) * 100;
                                                $set('margin_percent_input', (int) round($marginPercent));
                                            }
                                            
                                            // Recalculate line totals to ensure they're correct based on current values
                                            $this->calculateItemTotals($set, $get);
                                        })
                                        ->afterStateUpdated(function (Set $set, Get $get, ?float $state): void {
                                            $marginPercent = $state ?? 0;
                                            $costPrice = (float) ($get('cost_price') ?? 0);
                                            $taxRate = (float) ($get('tax_rate') ?? 0);
                                            $isTaxInclusive = (bool) ($get('is_tax_inclusive') ?? false);

                                            if ($costPrice > 0 && $marginPercent >= 0 && $marginPercent < 100) {
                                                // Calculate selling price from margin on selling: selling = cost / (1 - margin%/100)
                                                // Formula: margin% = (selling - cost) / selling × 100
                                                // Solving for selling: selling = cost / (1 - margin%/100)
                                                $unitPriceExcTax = round($costPrice / (1 - $marginPercent / 100), 0);
                                                
                                                // unit_price always represents the net price (Selling Price Net)
                                                // The "+Tax" checkbox only affects whether tax is added to line total
                                                $unitPrice = $unitPriceExcTax;
                                                
                                                $set('unit_price', $unitPrice);
                                                $set('unit_price_exc_tax', $unitPriceExcTax);
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

                                    // Calculate margin on selling: margin% = (total_margin / total_selling) * 100
                                    $totalSelling = 0.0;
                                    foreach ($items as $item) {
                                        $qty = (float) ($item['quantity'] ?? 0);
                                        $unitPriceExcTax = (float) ($item['unit_price_exc_tax'] ?? 0);
                                        $totalSelling += $unitPriceExcTax * $qty;
                                    }
                                    $marginPercent = $totalSelling > 0 ? ($totalMargin / $totalSelling) * 100 : 0;

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

    /**
     * Get the form schema for Buyer PO upload.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function getBuyerPoUploadFormSchema(): array
    {
        return [
            Section::make('Buyer PO Files')
                ->schema([
                    FileUpload::make('buyer_po_files')
                        ->label('Upload Buyer PO Files')
                        ->helperText('Upload purchasing order from buyer as reference (PDF, Excel, Word, Images)')
                        ->hint('Maximum file size: 2MB. Files exceeding this limit will be rejected.')
                        ->hintColor('warning')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
                            'application/vnd.ms-excel', // xls
                            'image/png',
                            'image/jpeg',
                            'image/jpg',
                            'application/msword', // doc
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
                        ])
                        ->disk('local')
                        ->directory('buyer-quotes/po-files')
                        ->visibility('private')
                        ->downloadable()
                        ->openable()
                        ->previewable()
                        ->multiple()
                        ->maxFiles(10)
                        ->maxSize(2048) // 2MB in KB - validation error will show if exceeded
                        ->validationMessages([
                            'max' => 'The file size must not exceed 2MB. Please compress or resize your file before uploading.',
                        ])
                        ->dehydrated(false)
                        ->afterStateUpdated(function ($state, $record, $set) {
                            // Process uploaded files immediately when they're uploaded
                            if ($record && $record->exists && $state && is_array($state) && ! empty($state)) {
                                foreach ($state as $file) {
                                    if (is_string($file)) {
                                        // Filament stores files relative to storage/app, so the path is already correct
                                        $filePath = storage_path('app/'.ltrim($file, '/'));

                                        if (file_exists($filePath)) {
                                            try {
                                                $media = $record->addMedia($filePath)
                                                    ->toMediaCollection('buyer_po');

                                                // Refresh the record to load new media
                                                $record->refresh();
                                            } catch (\Exception $e) {
                                                // Log error for debugging
                                                \Illuminate\Support\Facades\Log::error('Failed to add Buyer PO media: '.$e->getMessage(), [
                                                    'file' => $file,
                                                    'filePath' => $filePath,
                                                    'exists' => file_exists($filePath),
                                                ]);
                                            }
                                        } else {
                                            \Illuminate\Support\Facades\Log::warning('Buyer PO file not found: '.$filePath);
                                        }
                                    }
                                }
                            }
                        }),
                    ViewField::make('buyer_po_list')
                        ->label('Uploaded Buyer PO Files')
                        ->view('filament.forms.components.buyer-po-list')
                        ->visible(fn (?BuyerQuote $record): bool => $record !== null && $record->exists)
                        ->dehydrated(false)
                        ->live(),
                ]),
        ];
    }

    /**
     * Get the form schema for viewing Buyer PO files only.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function getBuyerPoViewFormSchema(): array
    {
        return [
            Section::make('Buyer PO Files')
                ->schema([
                    ViewField::make('buyer_po_list')
                        ->label('Uploaded Buyer PO Files')
                        ->view('filament.forms.components.buyer-po-list')
                        ->visible(fn (?BuyerQuote $record): bool => $record !== null && $record->exists)
                        ->dehydrated(false)
                        ->live(),
                ]),
        ];
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
                    
                    ->sortable()
                    ->description(fn (BuyerQuote $record): string => 'v'.$record->version),
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    
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

                        // Get all items from supplier quotes with status SELECTED for this request
                        $selectedSupplierQuoteItems = \App\Models\SupplierQuoteItem::query()
                            ->whereHas('supplierQuote', fn ($q) => $q->where('request_id', $request->getKey())
                                ->where('status', SupplierQuoteStatus::SELECTED))
                            ->with(['supplierQuote.supplier', 'requestItem.article', 'requestItem.unitOfMeasure'])
                            ->get();

                        foreach ($selectedSupplierQuoteItems as $supplierQuoteItem) {
                            $requestItem = $supplierQuoteItem->requestItem;
                            if ($requestItem === null) {
                                continue;
                            }

                            $costPrice = (float) $supplierQuoteItem->unit_price_exc_tax;
                            $supplierQuoteItemId = $supplierQuoteItem->getKey();
                            $supplierName = $supplierQuoteItem->supplierQuote->supplier->name ?? null;

                            // Calculate tax-related values
                            $taxRate = $defaultTaxCode !== null ? (float) $defaultTaxCode->rate : 0.0;
                            $addTax = $defaultTaxCode !== null && $defaultTaxCode->is_inclusive_default;
                            $quantity = (float) $requestItem->quantity;

                            // Calculate selling price with default margin on selling (NET price)
                            // Formula: margin% = (selling - cost) / selling × 100
                            // Solving for selling: selling = cost / (1 - margin%/100)
                            $unitPriceExcTax = $costPrice > 0 && $defaultMarginPercent < 100
                                ? round($costPrice / (1 - $defaultMarginPercent / 100), 0)
                                : 0.0;

                            // unit_price always represents the net price (Selling Price Net), regardless of tax inclusivity
                            // The +Tax checkbox only affects whether tax is added to line_total
                            $unitPrice = round($unitPriceExcTax, 0);

                            // Line subtotal is quantity * net price (amount before tax)
                            $lineSubtotal = $quantity * $unitPriceExcTax;

                            // Calculate tax if tax rate > 0 (tax should always be calculated when tax code is selected)
                            if ($taxRate > 0) {
                                // Calculate tax amount
                                $lineTax = $lineSubtotal * $taxRate / 100;
                                
                                if ($addTax) {
                                    // Tax is added on top of the net price (line_total = subtotal + tax)
                                    $lineTotal = $lineSubtotal + $lineTax;
                                } else {
                                    // Tax is exclusive - line total equals subtotal (no tax added)
                                    $lineTax = 0;
                                    $lineTotal = $lineSubtotal;
                                }
                            } else {
                                $lineTax = 0;
                                $lineTotal = $lineSubtotal;
                            }

                            // Calculate margin amount and percentage for storage
                            $marginAmount = $unitPriceExcTax - $costPrice;
                            $marginPercent = $unitPriceExcTax > 0 ? ($marginAmount / $unitPriceExcTax) * 100 : 0;

                            $items[] = [
                                'request_item_id' => $requestItem->getKey(),
                                'article_id' => $requestItem->article_id,
                                'supplier_quote_item_id' => $supplierQuoteItemId,
                                'from_supplier' => $supplierName,
                                'description' => $requestItem->article !== null ? $requestItem->article->name : $requestItem->description,
                                'quantity' => (string) $requestItem->quantity,
                                'unit_of_measure_id' => $requestItem->unit_of_measure_id,
                                'unit' => $requestItem->unitOfMeasure?->code ?? $requestItem->unit?->value ?? 'pcs',
                                'cost_price' => (string) $costPrice,
                                'unit_price' => (string) $unitPrice,
                                'unit_price_exc_tax' => (string) round($unitPriceExcTax, 0),
                                'tax_code_id' => $defaultTaxCode?->getKey(),
                                'tax_rate' => (string) $taxRate,
                                'tax_amount' => (string) round($lineTax / max($quantity, 0.0001), 4),
                                'is_tax_inclusive' => $addTax,
                                'line_subtotal' => (string) round($lineSubtotal, 4),
                                'line_tax' => (string) round($lineTax, 4),
                                'line_total' => (string) round($lineTotal, 0),
                                'margin_amount' => (string) round($marginAmount, 4),
                                'margin_percent' => (string) round($marginPercent, 4),
                                'margin_percent_input' => (string) (int) ceil($defaultMarginPercent),
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
                    ->after(function (BuyerQuote $record, array $data) use ($request): void {
                        // Process uploaded Buyer PO files for new records (create mode)
                        // For edit mode, files are processed in afterStateUpdated hook
                        // Note: Since dehydrated(false), files won't be in $data, but they're processed immediately on upload

                        // Refresh media relationship to ensure it's loaded
                        $record->load('media');

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

                            // Get all items from supplier quotes with status SELECTED for this request
                            $selectedSupplierQuoteItems = \App\Models\SupplierQuoteItem::query()
                                ->whereHas('supplierQuote', fn ($q) => $q->where('request_id', $request->getKey())
                                    ->where('status', SupplierQuoteStatus::SELECTED))
                                ->with(['requestItem.article', 'requestItem.unitOfMeasure'])
                                ->get();

                            foreach ($selectedSupplierQuoteItems as $supplierQuoteItem) {
                                $requestItem = $supplierQuoteItem->requestItem;
                                if ($requestItem === null) {
                                    continue;
                                }

                                $costPrice = (float) $supplierQuoteItem->unit_price_exc_tax;
                                $supplierQuoteItemId = $supplierQuoteItem->getKey();

                                // Calculate tax-related values
                                $taxRate = $defaultTaxCode !== null ? (float) $defaultTaxCode->rate : 0.0;
                                $addTax = $defaultTaxCode !== null && $defaultTaxCode->is_inclusive_default;
                                $quantity = (float) $requestItem->quantity;

                                // Calculate selling price with default margin (NET price)
                                // Calculate selling price with default margin on selling (NET price)
                                // Formula: margin% = (selling - cost) / selling × 100
                                // Solving for selling: selling = cost / (1 - margin%/100)
                                $unitPriceExcTax = $costPrice > 0 && $defaultMarginPercent < 100
                                    ? round($costPrice / (1 - $defaultMarginPercent / 100), 4)
                                    : 0.0;

                                // If tax is inclusive, unit_price should include tax; otherwise unit_price = net price
                                if ($addTax && $taxRate > 0) {
                                    $unitPrice = round($unitPriceExcTax * (1 + $taxRate / 100), 4);
                                } else {
                                    $unitPrice = $unitPriceExcTax;
                                }

                                // Line subtotal is quantity * net price (amount before tax)
                                $lineSubtotal = $quantity * $unitPriceExcTax;

                                // Calculate tax if tax rate > 0 (tax should always be calculated when tax code is selected)
                                if ($taxRate > 0) {
                                    if ($addTax) {
                                        // Tax is inclusive - line_total includes tax, line_subtotal is extracted
                                        $lineTotal = $quantity * $unitPrice;
                                        $lineTax = $lineTotal - $lineSubtotal;
                                    } else {
                                        // Tax is exclusive - add tax on top
                                    $lineTax = $lineSubtotal * $taxRate / 100;
                                    $lineTotal = $lineSubtotal + $lineTax;
                                    }
                                } else {
                                    $lineTax = 0;
                                    $lineTotal = $lineSubtotal;
                                }

                                // Calculate margin: ((selling_price - cost_price) / selling_price) * 100
                                $marginAmount = $unitPriceExcTax - $costPrice;
                                $marginPercent = $unitPriceExcTax > 0 ? ($marginAmount / $unitPriceExcTax) * 100 : 0;

                                $record->items()->create([
                                    'request_item_id' => $requestItem->getKey(),
                                    'article_id' => $requestItem->article_id,
                                    'supplier_quote_item_id' => $supplierQuoteItemId,
                                    'description' => $requestItem->article !== null ? $requestItem->article->name : $requestItem->description,
                                    'quantity' => $requestItem->quantity,
                                    'unit_of_measure_id' => $requestItem->unit_of_measure_id,
                                    'unit' => $requestItem->unitOfMeasure?->code ?? $requestItem->unit?->value ?? 'pcs',
                                    'cost_price' => $costPrice,
                                    'unit_price' => $unitPrice,
                                    'unit_price_exc_tax' => round($unitPriceExcTax, 0),
                                    'tax_code_id' => $defaultTaxCode?->getKey(),
                                    'tax_rate' => $taxRate,
                                    'tax_amount' => round($lineTax / max($quantity, 0.0001), 4),
                                    'is_tax_inclusive' => $addTax,
                                    'line_subtotal' => round($lineSubtotal, 4),
                                    'line_tax' => round($lineTax, 4),
                                    'line_total' => round($lineTotal, 0),
                                    'margin_amount' => round($marginAmount, 4),
                                    'margin_percent' => round($marginPercent, 4),
                                    'sort_order' => $sortOrder++,
                                ]);
                            }
                        }
                    })
                    ->createAnother(false),
                Action::make('createPnl')
                    ->label(function () use ($request): string {
                        return $request->profitAndLosses()->exists() ? 'View PNL' : 'Create PNL';
                    })
                    ->icon('heroicon-o-chart-bar')
                    ->size(Size::Small)
                    ->color('success')
                    ->visible(fn () => $request->buyerQuotes()->exists())
                    ->url(function () use ($request): ?string {
                        // If PNL exists, return URL to view page
                        if ($request->profitAndLosses()->exists()) {
                            $pnl = $request->profitAndLosses()->latest()->first();
                            return ProfitAndLossResource::getUrl('view', ['record' => $pnl]);
                        }
                        return null;
                    })
                    ->modalWidth('xl')
                    ->form(function () use ($request): array {
                        // Only show form if PNL doesn't exist
                        if ($request->profitAndLosses()->exists()) {
                            return [];
                        }

                        return [
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
                                    KeyAccountSelect::makeWithRelationship(
                                        'prepared_by_id',
                                        'Prepared By',
                                        'preparedBy',
                                        CentralPurchasingRole::KEY_ACCOUNT,
                                        fn () => $request->buyer_id
                                    ),
                                    KeyAccountSelect::makeWithRelationship(
                                        'dept_head_sales_id',
                                        'Dept Head of Sales',
                                        'deptHeadSales',
                                        CentralPurchasingRole::DEPT_HEAD_SALES,
                                        null
                                    ),
                                    KeyAccountSelect::makeWithRelationship(
                                        'deputy_director_id',
                                        'Deputy Director',
                                        'deputyDirector',
                                        CentralPurchasingRole::DEPUTY_DIRECTOR,
                                        null
                                    ),
                                    KeyAccountSelect::makeWithRelationship(
                                        'approved_by_id',
                                        'Approved By',
                                        'approvedBy',
                                        CentralPurchasingRole::DIRECTOR,
                                        null
                                    ),
                                ])
                                ->columns(2),
                        ];
                    })
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
                            'dept_head_sales_id' => $data['dept_head_sales_id'] ?? null,
                            'deputy_director_id' => $data['deputy_director_id'] ?? null,
                            'approved_by_id' => $data['approved_by_id'] ?? null,
                        ]);

                        Notification::make()
                            ->title('PNL created')
                            ->body("PNL {$pnl->pnl_number} has been created successfully.")
                            ->success()
                            ->send();

                        redirect(ProfitAndLossResource::getUrl('view', ['record' => $pnl]));
                    }),
                Action::make('send')
                    ->label('Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->size(Size::Small)
                    ->color('info')
                    ->visible(fn () => $request->buyerQuotes()->exists())
                    ->disabled(function () use ($request): bool {
                        // Disable if PNL is not created
                        return ! $request->profitAndLosses()->exists();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Send this quote?')
                    ->modalDescription(function () use ($request): string {
                        // Find the latest valid buyer quote (not rejected/superseded)
                        $buyerQuote = $request->buyerQuotes()
                            ->whereNotIn('status', [BuyerQuoteStatus::REJECTED, BuyerQuoteStatus::SUPERSEDED])
                            ->latest()
                            ->first();
                        
                        if ($buyerQuote === null) {
                            return 'No valid buyer quote found.';
                        }
                        
                        $buyerEmail = $buyerQuote->buyer->email ?? null;
                        $buyerName = $buyerQuote->buyer->name ?? 'Unknown';
                        $description = 'This will mark the quote as sent and set the issue date to today.';
                        
                        if (empty($buyerEmail)) {
                            $description .= "\n\n⚠️ **Warning:** The buyer ({$buyerName}) does not have an email address configured. The quote will be marked as sent, but no email will be sent.";
                        } else {
                            $description .= "\n\n📧 Email will be sent to: {$buyerEmail}";
                        }
                        
                        return $description;
                    })
                    ->action(function () use ($request): void {
                        // Find the latest valid buyer quote (not rejected/superseded)
                        $buyerQuote = $request->buyerQuotes()
                            ->whereNotIn('status', [BuyerQuoteStatus::REJECTED, BuyerQuoteStatus::SUPERSEDED])
                            ->latest()
                            ->first();
                        
                        if ($buyerQuote === null) {
                            Notification::make()
                                ->title('No quote found')
                                ->body('No valid buyer quote found to send.')
                                ->warning()
                                ->send();
                            return;
                        }
                        
                        // Check if quote can be sent
                        if (! $buyerQuote->status->canSend()) {
                            Notification::make()
                                ->title('Cannot send quote')
                                ->body('This quote cannot be sent in its current status.')
                                ->warning()
                                ->send();
                            return;
                        }
                        
                        $buyerQuote->markAsSent();

                        // Send email to buyer
                        $buyerEmail = $buyerQuote->buyer->email ?? null;
                        $buyerName = $buyerQuote->buyer->name ?? 'Buyer';
                        
                        if (empty($buyerEmail)) {
                            Notification::make()
                                ->title('Quote marked as sent')
                                ->body("Quote has been marked as sent, but no email was sent because the buyer ({$buyerName}) does not have an email address configured.")
                                ->warning()
                                ->send();
                            return;
                        }

                        try {
                            $emailService = app(\App\Services\Email\EmailTemplateService::class);
                            $settings = $buyerQuote->team->getErpSettings();
                            $emailService->sendWithTeamSettings(
                                $buyerQuote->team,
                                new \App\Mail\Erp\QuoteToBuyerMail($buyerQuote),
                                $buyerEmail,
                                $settings->email_template_buyer_quote, // Old system fallback
                                $settings->email_template_buyer_quote_id ?? null, // New system
                                \App\Models\EmailTemplate::TYPE_BUYER_QUOTE
                            );

                            Notification::make()
                                ->title('Quote sent')
                                ->body("Quote has been sent successfully to {$buyerEmail}.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to send buyer quote email', [
                                'quote_id' => $buyerQuote->id,
                                'buyer_email' => $buyerEmail,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);

                            Notification::make()
                                ->title('Failed to send email')
                                ->body("Quote has been marked as sent, but the email could not be sent to {$buyerEmail}. Error: ".$e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->visible(fn (?BuyerQuote $record): bool => $record !== null && ! $record->status->canEdit())
                        ->modalWidth('7xl')
                        ->form(fn (): array => $this->getFormSchema()),
                    EditAction::make()
                        ->visible(fn (?BuyerQuote $record): bool => $record !== null && $record->status->canEdit())
                        ->modalWidth('7xl')
                        ->form(fn (): array => $this->getFormSchema())
                        ->mutateFormDataUsing(function (array $data): array {
                            // Ensure unit_price_exc_tax matches unit_price for all items (both should be net price)
                            if (isset($data['items']) && is_array($data['items'])) {
                                foreach ($data['items'] as $key => $item) {
                                    if (isset($item['unit_price']) && (float) $item['unit_price'] > 0) {
                                        $data['items'][$key]['unit_price_exc_tax'] = round((float) $item['unit_price'], 0);
                                    }
                                }
                            }
                            return $data;
                        })
                        ->after(function (BuyerQuote $record): void {
                            // Ensure all items have correct unit_price_exc_tax after save
                            $record->load('items');
                            foreach ($record->items as $item) {
                                if ((float) $item->unit_price !== (float) $item->unit_price_exc_tax && (float) $item->unit_price > 0) {
                                    $item->recalculatePrices();
                                    $item->saveQuietly();
                                }
                            }
                            // Recalculate quote totals
                            $record->recalculateTotals();
                        }),
                    \Filament\Actions\DeleteAction::make()
                        ->hidden(function (?BuyerQuote $record): bool {
                            if ($record === null) {
                                return true;
                            }
                            // Override default trashed check to also check status
                            if (method_exists($record, 'trashed') && $record->trashed()) {
                                return true;
                            }
                            return ! $record->status->canEdit();
                        }),
                    DownloadPdfAction::make()
                        ->label('PDF'),
                    Action::make('uploadPo')
                        ->label(function (BuyerQuote $record): string {
                            // Load media if not already loaded
                            if (! $record->relationLoaded('media')) {
                                $record->load('media');
                            }
                            return $record->getMedia('buyer_po')->isNotEmpty() ? 'View PO' : 'Upload PO';
                        })
                        ->icon(function (BuyerQuote $record): string {
                            // Load media if not already loaded
                            if (! $record->relationLoaded('media')) {
                                $record->load('media');
                            }
                            return $record->getMedia('buyer_po')->isNotEmpty() ? 'heroicon-o-eye' : 'heroicon-o-document-arrow-up';
                        })
                        ->color('gray')
                        ->visible(function (?BuyerQuote $record): bool {
                            if ($record === null) {
                                return false;
                            }
                            // Show when status is SENT (for upload) or ACCEPTED (for view)
                            return $record->status === BuyerQuoteStatus::SENT || $record->status === BuyerQuoteStatus::ACCEPTED;
                        })
                        ->slideOver()
                        ->form(function (BuyerQuote $record): array {
                            // Load media if not already loaded
                            if (! $record->relationLoaded('media')) {
                                $record->load('media');
                            }
                            // Show view-only form if files exist, otherwise show upload form
                            return $record->getMedia('buyer_po')->isNotEmpty() 
                                ? $this->getBuyerPoViewFormSchema() 
                                : $this->getBuyerPoUploadFormSchema();
                        })
                        ->fillForm(function (BuyerQuote $record): array {
                            // Ensure media relationship is loaded
                            $record->load('media');
                            
                            // Check if PO files already exist and status is SENT - auto-update status
                            // This handles cases where files were uploaded but status wasn't updated
                            if ($record->status === BuyerQuoteStatus::SENT && $record->getMedia('buyer_po')->isNotEmpty()) {
                                try {
                                    $record->markAsAccepted();
                                    $record->refresh();
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('Failed to auto-update quote status when PO files exist', [
                                        'quote_id' => $record->id,
                                        'status' => $record->status->value,
                                        'has_files' => $record->getMedia('buyer_po')->isNotEmpty(),
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                            
                            return [];
                        })
                        ->after(function (BuyerQuote $record): void {
                            // Store original status before refresh
                            $originalStatus = $record->status;
                            
                            // Refresh media relationship after upload to get latest files
                            // Use fresh() to ensure we get the latest data from database
                            $record = $record->fresh(['media']);
                            $record->load('media');
                            
                            // Check if files exist in media collection
                            $hasFiles = $record->getMedia('buyer_po')->isNotEmpty();
                            
                            // If status is SENT and files exist, change status to ACCEPTED
                            // Files are added to media collection by afterStateUpdated callback when selected
                            if ($originalStatus === BuyerQuoteStatus::SENT && $hasFiles) {
                                try {
                                    // Use fresh instance to ensure we're working with latest data
                                    $freshRecord = BuyerQuote::find($record->id);
                                    if ($freshRecord && $freshRecord->status === BuyerQuoteStatus::SENT) {
                                        $freshRecord->markAsAccepted();
                                        
                                        Notification::make()
                                            ->title('PO uploaded')
                                            ->body('Purchase order has been uploaded and quote status changed to Accepted.')
                                            ->success()
                                            ->send();
                                    }
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('Failed to mark quote as accepted after PO upload', [
                                        'quote_id' => $record->id,
                                        'status' => $record->status->value,
                                        'has_files' => $hasFiles,
                                        'error' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString(),
                                    ]);
                                    
                                    Notification::make()
                                        ->title('PO uploaded')
                                        ->body('Purchase order has been uploaded, but failed to update quote status. Please try again.')
                                        ->warning()
                                        ->send();
                                }
                            }
                        }),
                    Action::make('resend')
                        ->label('Resend')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->visible(fn (?BuyerQuote $record): bool => $record !== null && $record->status === BuyerQuoteStatus::SENT)
                        ->requiresConfirmation()
                        ->modalHeading('Resend quote email?')
                        ->modalDescription(function (BuyerQuote $record): string {
                            $buyerEmail = $record->buyer->email ?? null;
                            $buyerName = $record->buyer->name ?? 'Unknown';
                            $description = 'This will resend the quote email to the buyer without changing the quote status.';
                            
                            if (empty($buyerEmail)) {
                                $description .= "\n\n⚠️ **Warning:** The buyer ({$buyerName}) does not have an email address configured. No email will be sent.";
                            } else {
                                $description .= "\n\n📧 Email will be sent to: {$buyerEmail}";
                            }
                            
                            return $description;
                        })
                        ->action(function (BuyerQuote $record): void {
                            // Resend email to buyer (without changing status)
                            $buyerEmail = $record->buyer->email ?? null;
                            $buyerName = $record->buyer->name ?? 'Buyer';
                            
                            if (empty($buyerEmail)) {
                                Notification::make()
                                    ->title('Cannot resend email')
                                    ->body("The buyer ({$buyerName}) does not have an email address configured.")
                                    ->warning()
                                    ->send();
                                return;
                            }

                            try {
                                $emailService = app(\App\Services\Email\EmailTemplateService::class);
                                $settings = $record->team->getErpSettings();
                                $emailService->sendWithTeamSettings(
                                    $record->team,
                                    new \App\Mail\Erp\QuoteToBuyerMail($record),
                                    $buyerEmail,
                                    $settings->email_template_buyer_quote, // Old system fallback
                                    $settings->email_template_buyer_quote_id ?? null, // New system
                                    \App\Models\EmailTemplate::TYPE_BUYER_QUOTE
                                );

                                Notification::make()
                                    ->title('Email resent')
                                    ->body("Quote email has been resent successfully to {$buyerEmail}.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\Log::error('Failed to resend buyer quote email', [
                                    'quote_id' => $record->id,
                                    'buyer_email' => $buyerEmail,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);

                                Notification::make()
                                    ->title('Failed to resend email')
                                    ->body("The email could not be sent to {$buyerEmail}. Error: ".$e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('accept')
                        ->label('Accept')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(function (?BuyerQuote $record): bool {
                            // Hide Accept button when status is SENT (Upload PO replaces it)
                            // Accept button is only available for SENT status, but we're replacing it with Upload PO
                            // So we hide it completely since Upload PO handles the acceptance
                            return false;
                        })
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
                        ->visible(fn (?BuyerQuote $record): bool => $record !== null && $record->status === BuyerQuoteStatus::SENT)
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
                        ->visible(fn (?BuyerQuote $record): bool => $record !== null && ! $record->status->canEdit())
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
                        ->visible(fn (?BuyerQuote $record): bool => $record !== null && $record->status->isActive())
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
                ]),
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
     * When is_tax_inclusive is true, unit_price includes tax.
     * When is_tax_inclusive is false, unit_price is net price and tax is added on top.
     */
    private function calculateItemTotals(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $unitPriceExcTaxStored = (float) ($get('unit_price_exc_tax') ?? 0);
        $costPrice = (float) ($get('cost_price') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);
        $isTaxInclusive = (bool) ($get('is_tax_inclusive') ?? false);

        // unit_price always represents the net price (Selling Price Net), regardless of tax checkbox
        // unit_price_exc_tax should always equal unit_price (they're the same - net price before tax)
        // Always use unit_price as the source of truth for net price
        if ($unitPrice > 0) {
            $unitPriceExcTax = round($unitPrice, 0);
        } elseif ($unitPriceExcTaxStored > 0) {
            // Fallback to stored value if unit_price is not set
            $unitPriceExcTax = $unitPriceExcTaxStored;
        } else {
            $unitPriceExcTax = 0;
        }

        // Calculate line totals based on tax inclusivity
        // When is_tax_inclusive is true (checkbox checked), tax is added to line total
        // When is_tax_inclusive is false (checkbox unchecked), no tax is added
        $lineSubtotal = $quantity * $unitPriceExcTax;
        
        if ($isTaxInclusive && $taxRate > 0) {
            // Tax is added on top of the net price
            $lineTax = $lineSubtotal * $taxRate / 100;
            $lineTotal = $lineSubtotal + $lineTax;
        } else {
            // No tax added - line total equals line subtotal
            $lineTax = 0;
            $lineTotal = $lineSubtotal;
        }

        // Calculate margin on selling: ((selling_price - cost_price) / selling_price) * 100
        $marginAmount = $unitPriceExcTax - $costPrice;
        $marginPercent = $unitPriceExcTax > 0 ? ($marginAmount / $unitPriceExcTax) * 100 : 0;

        $set('unit_price_exc_tax', round($unitPriceExcTax, 0));
        $set('line_subtotal', round($lineSubtotal, 0));
        $set('line_tax', round($lineTax, 0));
        $set('line_total', round($lineTotal, 0));
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
