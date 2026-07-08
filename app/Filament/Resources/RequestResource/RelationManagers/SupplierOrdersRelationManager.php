<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Actions\Erp\SendSupplierOrderToSupplier;
use App\Enums\BuyerQuoteStatus;
use App\Enums\OrderStatus;
use App\Enums\RequestStage;
use App\Enums\SupplierOrderSendOutcome;
use App\Enums\SupplierQuoteStatus;
use App\Filament\Actions\ApproveSupplierOrderAction;
use App\Filament\Actions\DownloadPdfAction;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Mail\Erp\PurchaseOrderToSupplierMail;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\SupplierQuote;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Services\Email\EmailTemplateService;
use App\Support\SafeCast;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Support\Facades\Log;

final class SupplierOrdersRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'supplierOrders';

    protected static ?string $title = 'Supplier Orders';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-shopping-cart';

    protected static function getAssociatedStage(): RequestStage
    {
        return RequestStage::PREPARING_SUPPLIER_ORDER;
    }

    protected static function getBaseTabTitle(): string
    {
        return 'Supplier Orders';
    }

    public function form(Schema $schema): Schema
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $schema
            ->columns(1)
            ->components([
                Section::make('Order Details')
                    ->columnSpanFull()
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
                                            $supplier = Company::query()->find(SafeCast::toInt($supplierId));
                                            $isTaxable = $supplier->is_taxable ?? true;
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
                                                    $set("items.{$index}.unit_price_exc_tax", round($unitPrice, 4));
                                                    $set("items.{$index}.tax_amount", 0);
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
                                Select::make('supplier_quote_id')
                                    ->label('From Quote')
                                    ->options(fn (): array => SupplierQuote::query()
                                        ->where('request_id', $request->getKey())
                                        ->where('status', SupplierQuoteStatus::SELECTED)
                                        ->get()
                                        ->mapWithKeys(fn (SupplierQuote $quote): array => [
                                            $quote->getKey() => "[{$quote->quote_number}] {$quote->supplier->name}",
                                        ])
                                        ->all())

                                    ->selectablePlaceholder(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                                        if ($state === null) {
                                            return;
                                        }
                                        $quote = SupplierQuote::with(['items', 'supplier', 'currency'])->find($state);
                                        if ($quote !== null) {
                                            $set('supplier_id', $quote->supplier_id);
                                            $set('currency_id', $quote->currency_id);
                                            $set('exchange_rate', $quote->exchange_rate);
                                            $set('notes', $quote->notes);
                                        }
                                    })
                                    ->helperText('Select a quote to copy values'),
                                Select::make('status')
                                    ->options(collect(OrderStatus::cases())
                                        ->mapWithKeys(fn (OrderStatus $status): array => [$status->value => $status->getSupplierOrderLabel()])
                                        ->all())
                                    ->default(OrderStatus::DRAFT)
                                    ->required()
                                    ->selectablePlaceholder(false),
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
                                    ->selectablePlaceholder(false),
                                TextInput::make('exchange_rate')
                                    ->label('Exchange Rate')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->step(0.00000001)
                                    ->helperText('Rate to convert to base currency'),
                                DatePicker::make('expected_delivery_date')
                                    ->label('Expected Delivery'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('payment_terms_days')
                                    ->label('Payment Terms (Days)')
                                    ->numeric()
                                    ->default(30),
                                TextInput::make('payment_terms_text')
                                    ->label('Payment Terms Text')
                                    ->placeholder('e.g., Net 30'),
                                Placeholder::make('po_number_display')
                                    ->label('PO Number')
                                    ->content(fn (?SupplierOrder $record): string => $record->po_number ?? 'Auto-generated'),
                            ]),
                    ]),

                Section::make('Line Items')
                    ->columnSpanFull()
                    ->schema([
                        ViewField::make('hierarchical_line_items')
                            ->label('')
                            ->view('filament.infolists.components.supplier-order-items')
                            ->visible(fn (?SupplierOrder $record): bool => $record !== null && ! $record->is_editable)
                            ->dehydrated(false),
                        Repeater::make('items')
                            ->relationship()
                            ->visible(fn (?SupplierOrder $record): bool => $record === null || $record->is_editable)
                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        Select::make('request_item_id')
                                            ->label('Request Item')
                                            ->options(fn (): array => $request->items()
                                                ->whereNull('parent_id') // Only show main items, not child items
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
                                        Hidden::make('unit_price_exc_tax')
                                            ->dehydrated(),
                                        Hidden::make('tax_amount')
                                            ->dehydrated(),
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
                                            ->afterStateHydrated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get))
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
                                        TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->step(0.0001)
                                            ->columnSpan(3)
                                            ->live(onBlur: true)
                                            ->afterStateHydrated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get))
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
                                        TextInput::make('tax_rate')
                                            ->label('Tax %')
                                            ->numeric()
                                            ->default(0)
                                            ->step(0.01)
                                            ->columnSpan(2)
                                            ->disabled()
                                            ->dehydrated()
                                            ->visible(fn (Get $get): bool => $this->isSupplierTaxable($get))
                                            ->afterStateHydrated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                        TextInput::make('line_total')
                                            ->label('Line Total')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(4),
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
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->live()
                                    ->content(function (Get $get): string {
                                        // Calculate from form state if creating/editing
                                        /** @var array<int, array<string, mixed>> $items */
                                        $items = $get('items') ?? [];
                                        if (! empty($items)) {
                                            $subtotal = 0.0;
                                            foreach ($items as $item) {
                                                $quantity = (float) ($item['quantity'] ?? 0);
                                                $unitPriceExcTax = (float) ($item['unit_price_exc_tax'] ?? $item['unit_price'] ?? 0);
                                                $subtotal += $quantity * $unitPriceExcTax;
                                            }

                                            $currencyId = $get('currency_id');
                                            /** @var Currency|null $currency */
                                            $currency = $currencyId !== null ? Currency::find($currencyId) : null;

                                            return $currency instanceof Currency ? $currency->formatNumber($subtotal) : number_format($subtotal, 2);
                                        }

                                        // Fallback to record values
                                        $record = $this->getMountedTableActionRecord();

                                        return $record instanceof \App\Models\SupplierOrder
                                            ? ($record->currency?->formatNumber((float) $record->subtotal) ?? number_format((float) $record->subtotal, 2))
                                            : '0,-';
                                    }),
                                Placeholder::make('tax_total_display')
                                    ->label('Tax Total')
                                    ->live()
                                    ->content(function (Get $get): string {
                                        // Calculate from form state if creating/editing
                                        /** @var array<int, array<string, mixed>> $items */
                                        $items = $get('items') ?? [];
                                        if (! empty($items)) {
                                            $taxTotal = 0.0;
                                            foreach ($items as $item) {
                                                $quantity = (float) ($item['quantity'] ?? 0);
                                                $taxAmount = (float) ($item['tax_amount'] ?? 0);
                                                $taxTotal += $quantity * $taxAmount;
                                            }

                                            $currencyId = $get('currency_id');
                                            /** @var Currency|null $currency */
                                            $currency = $currencyId !== null ? Currency::find($currencyId) : null;

                                            return $currency instanceof Currency ? $currency->formatNumber($taxTotal) : number_format($taxTotal, 2);
                                        }

                                        // Fallback to record values
                                        $record = $this->getMountedTableActionRecord();

                                        return $record instanceof \App\Models\SupplierOrder
                                            ? ($record->currency?->formatNumber((float) $record->tax_total) ?? number_format((float) $record->tax_total, 2))
                                            : '0,-';
                                    })
                                    ->visible(function (Get $get): bool {
                                        // Check form state first
                                        $supplierId = $get('supplier_id');
                                        if ($supplierId !== null) {
                                            $supplier = Company::query()->find(SafeCast::toInt($supplierId));

                                            return $supplier->is_taxable ?? true;
                                        }

                                        // Fallback to record
                                        $record = $this->getMountedTableActionRecord();

                                        return ! $record instanceof \App\Models\SupplierOrder || $this->isRecordSupplierTaxable($record);
                                    }),
                                Placeholder::make('total_display')
                                    ->label('Total')
                                    ->live()
                                    ->content(function (Get $get): string {
                                        // Calculate from form state if creating/editing
                                        /** @var array<int, array<string, mixed>> $items */
                                        $items = $get('items') ?? [];
                                        if (! empty($items)) {
                                            $total = 0.0;
                                            foreach ($items as $item) {
                                                $total += (float) ($item['line_total'] ?? 0);
                                            }

                                            $currencyId = $get('currency_id');
                                            /** @var Currency|null $currency */
                                            $currency = $currencyId !== null ? Currency::find($currencyId) : null;

                                            return $currency instanceof Currency ? $currency->format($total) : number_format($total, 2);
                                        }

                                        // Fallback to record values
                                        $record = $this->getMountedTableActionRecord();

                                        return $record instanceof \App\Models\SupplierOrder
                                            ? ($record->currency?->format((float) $record->total) ?? number_format((float) $record->total, 2))
                                            : '0,-';
                                    }),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('base_subtotal_display')
                                    ->label('Subtotal (Base)')
                                    ->live()
                                    ->content(function (Get $get): string {
                                        // Calculate from form state if creating/editing
                                        /** @var array<int, array<string, mixed>> $items */
                                        $items = $get('items') ?? [];
                                        if (! empty($items)) {
                                            $subtotal = 0.0;
                                            foreach ($items as $item) {
                                                $quantity = (float) ($item['quantity'] ?? 0);
                                                $unitPriceExcTax = (float) ($item['unit_price_exc_tax'] ?? $item['unit_price'] ?? 0);
                                                $subtotal += $quantity * $unitPriceExcTax;
                                            }

                                            $exchangeRate = (float) ($get('exchange_rate') ?? 1);
                                            $baseSubtotal = $subtotal * $exchangeRate;

                                            /** @var \App\Models\Team|null $team */
                                            $team = Filament::getTenant();
                                            $baseCurrency = $team?->getBaseCurrency();

                                            return $baseCurrency !== null
                                                ? $baseCurrency->formatNumber($baseSubtotal)
                                                : number_format($baseSubtotal, 2);
                                        }

                                        // Fallback to record values
                                        $record = $this->getMountedTableActionRecord();
                                        if (! $record instanceof \App\Models\SupplierOrder) {
                                            return '0,-';
                                        }
                                        /** @var \App\Models\Team|null $team */
                                        $team = Filament::getTenant();
                                        $baseCurrency = $team?->getBaseCurrency();

                                        return $baseCurrency !== null
                                            ? $baseCurrency->formatNumber((float) $record->base_subtotal)
                                            : number_format((float) $record->base_subtotal, 2);
                                    }),
                                Placeholder::make('base_tax_total_display')
                                    ->label('Tax Total (Base)')
                                    ->live()
                                    ->content(function (Get $get): string {
                                        // Calculate from form state if creating/editing
                                        /** @var array<int, array<string, mixed>> $items */
                                        $items = $get('items') ?? [];
                                        if (! empty($items)) {
                                            $taxTotal = 0.0;
                                            foreach ($items as $item) {
                                                $quantity = (float) ($item['quantity'] ?? 0);
                                                $taxAmount = (float) ($item['tax_amount'] ?? 0);
                                                $taxTotal += $quantity * $taxAmount;
                                            }

                                            $exchangeRate = (float) ($get('exchange_rate') ?? 1);
                                            $baseTaxTotal = $taxTotal * $exchangeRate;

                                            /** @var \App\Models\Team|null $team */
                                            $team = Filament::getTenant();
                                            $baseCurrency = $team?->getBaseCurrency();

                                            return $baseCurrency !== null
                                                ? $baseCurrency->formatNumber($baseTaxTotal)
                                                : number_format($baseTaxTotal, 2);
                                        }

                                        // Fallback to record values
                                        $record = $this->getMountedTableActionRecord();
                                        if (! $record instanceof \App\Models\SupplierOrder) {
                                            return '0,-';
                                        }
                                        /** @var \App\Models\Team|null $team */
                                        $team = Filament::getTenant();
                                        $baseCurrency = $team?->getBaseCurrency();

                                        return $baseCurrency !== null
                                            ? $baseCurrency->formatNumber((float) $record->base_tax_total)
                                            : number_format((float) $record->base_tax_total, 2);
                                    })
                                    ->visible(function (Get $get): bool {
                                        // Check form state first
                                        $supplierId = $get('supplier_id');
                                        if ($supplierId !== null) {
                                            $supplier = Company::query()->find(SafeCast::toInt($supplierId));

                                            return $supplier->is_taxable ?? true;
                                        }

                                        // Fallback to record
                                        $record = $this->getMountedTableActionRecord();

                                        return ! $record instanceof \App\Models\SupplierOrder || $this->isRecordSupplierTaxable($record);
                                    }),
                                Placeholder::make('base_total_display')
                                    ->label('Total (Base)')
                                    ->live()
                                    ->content(function (Get $get): string {
                                        // Calculate from form state if creating/editing
                                        /** @var array<int, array<string, mixed>> $items */
                                        $items = $get('items') ?? [];
                                        if (! empty($items)) {
                                            $total = 0.0;
                                            foreach ($items as $item) {
                                                $total += (float) ($item['line_total'] ?? 0);
                                            }

                                            $exchangeRate = (float) ($get('exchange_rate') ?? 1);
                                            $baseTotal = $total * $exchangeRate;

                                            /** @var \App\Models\Team|null $team */
                                            $team = Filament::getTenant();
                                            $baseCurrency = $team?->getBaseCurrency();

                                            return $baseCurrency !== null
                                                ? $baseCurrency->format($baseTotal)
                                                : number_format($baseTotal, 2);
                                        }

                                        // Fallback to record values
                                        $record = $this->getMountedTableActionRecord();
                                        if (! $record instanceof \App\Models\SupplierOrder) {
                                            return '0,-';
                                        }
                                        /** @var \App\Models\Team|null $team */
                                        $team = Filament::getTenant();
                                        $baseCurrency = $team?->getBaseCurrency();

                                        return $baseCurrency !== null
                                            ? $baseCurrency->format((float) $record->base_total)
                                            : number_format((float) $record->base_total, 2);
                                    }),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Notes')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Order Notes')
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
            ->recordTitleAttribute('po_number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('po_number')
                    ->label('PO #')

                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')

                    ->sortable(),
                TextColumn::make('supplierQuote.quote_number')
                    ->label('From Quote')
                    ->placeholder('Direct Order')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->getSupplierOrderLabel())
                    ->sortable(),
                TextColumn::make('approvals')
                    ->label('Approvals')
                    ->badge()
                    ->state(function (SupplierOrder $record): ?string {
                        if (! in_array($record->status, [OrderStatus::CONFIRMED, OrderStatus::APPROVED], true)) {
                            return null;
                        }

                        $recorded = ($record->approver_1_id !== null ? 1 : 0)
                            + ($record->approver_2_id !== null ? 1 : 0);

                        return $recorded.'/2';
                    })
                    ->color(fn (?string $state): string => $state === '2/2' ? 'success' : 'warning')
                    ->tooltip(function (SupplierOrder $record): string {
                        $names = array_filter([
                            $record->approver1?->name,
                            $record->approver2?->name,
                        ]);

                        if ($names === []) {
                            return 'No approvals recorded yet';
                        }

                        return 'Approved by: '.implode(', ', $names);
                    })
                    ->placeholder('—'),
                TextColumn::make('currency.code')
                    ->label('Currency')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (SupplierOrder $record): string => $record->currency !== null ? $record->currency->format((float) $record->total) : number_format((float) $record->total, 2))
                    ->sortable(),
                TextColumn::make('base_total')
                    ->label('Total (Base)')
                    ->formatStateUsing(function (SupplierOrder $record): string {
                        /** @var \App\Models\Team|null $team */
                        $team = Filament::getTenant();
                        $baseCurrency = $team?->getBaseCurrency();

                        return $baseCurrency !== null ? $baseCurrency->format((float) $record->base_total) : number_format((float) $record->base_total, 2);
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('exchange_rate')
                    ->label('Rate')
                    ->numeric(decimalPlaces: 4)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expected_delivery_date')
                    ->label('Expected Delivery')
                    ->date()
                    ->sortable(),
                TextColumn::make('ordered_at')
                    ->label('Ordered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(OrderStatus::cases())
                        ->mapWithKeys(fn (OrderStatus $status): array => [$status->value => $status->getSupplierOrderLabel()])
                        ->all()),
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
                Action::make('createFromBuyerOrder')
                    ->label('Create from Accepted Quote')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('info')
                    ->size(Size::Small)
                    ->modalHeading('Create Supplier Orders from Accepted Buyer Quote(s)')
                    ->modalDescription('Select a supplier first, then review the items. One purchase order will be created for the selected supplier.')
                    ->modalWidth('4xl')
                    ->form(function (): array {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();

                        $hasAcceptedQuote = BuyerQuote::query()
                            ->where('request_id', $request->getKey())
                            ->where('status', BuyerQuoteStatus::ACCEPTED)
                            ->exists();

                        if (! $hasAcceptedQuote) {
                            return [
                                Placeholder::make('no_quote')
                                    ->label('')
                                    ->content('No accepted buyer quote available.'),
                            ];
                        }

                        // Group quote items by supplier (from all accepted quotes)
                        $itemsBySupplier = $this->groupAcceptedBuyerQuoteItemsBySupplier($request);

                        if ($itemsBySupplier === []) {
                            return [
                                Placeholder::make('no_suppliers')
                                    ->label('')
                                    ->content('No items with assigned suppliers found. Please assign suppliers to request items first.'),
                            ];
                        }

                        // Build supplier options for select (only suppliers without an existing order)
                        $supplierOptions = [];
                        foreach ($itemsBySupplier as $supplierId => $data) {
                            /** @var Company $supplier */
                            $supplier = $data['supplier'];
                            $existingOrder = SupplierOrder::query()
                                ->where('request_id', $request->getKey())
                                ->where('supplier_id', $supplierId)
                                ->whereNotIn('status', [OrderStatus::CANCELLED])
                                ->exists();
                            if (! $existingOrder) {
                                $supplierOptions[$supplierId] = "[{$supplier->code}] {$supplier->name}";
                            }
                        }

                        $sections = [];

                        // Single supplier select at top
                        $sections[] = \Filament\Schemas\Components\Section::make()
                            ->schema([
                                Select::make('selected_supplier_id')
                                    ->label('Supplier to create PO for')
                                    ->options($supplierOptions)
                                    ->required()
                                    ->live()
                                    ->native(false)
                                    ->searchable()
                                    ->placeholder('Select a supplier...')
                                    ->helperText(empty($supplierOptions) ? 'All suppliers already have an order.' : null)
                                    ->disabled(empty($supplierOptions)),
                            ]);

                        // One section per supplier; visible only when that supplier is selected
                        foreach ($itemsBySupplier as $supplierId => $data) {
                            /** @var Company $supplier */
                            $supplier = $data['supplier'];
                            /** @var \Illuminate\Support\Collection<int, BuyerQuoteItem> $quoteItems */
                            $quoteItems = $data['items'];
                            $itemsToOrder = BuyerQuoteItem::filterForTotals($quoteItems);

                            $existingOrder = SupplierOrder::query()
                                ->where('request_id', $request->getKey())
                                ->where('supplier_id', $supplierId)
                                ->whereNotIn('status', [OrderStatus::CANCELLED])
                                ->exists();
                            $statusBadge = $existingOrder ? ' ⚠️ (Order exists)' : '';

                            $itemsHtml = $this->buildBuyerQuoteItemsPreviewHtml($quoteItems);

                            $sections[] = \Filament\Schemas\Components\Section::make("[{$supplier->code}] {$supplier->name}{$statusBadge}")
                                ->description($itemsToOrder->count().' item(s) to order')
                                ->schema([
                                    Placeholder::make("items_{$supplierId}")
                                        ->label('')
                                        ->content(new \Illuminate\Support\HtmlString($itemsHtml)),
                                ])
                                ->visible(fn (Get $get): bool => (int) ($get('selected_supplier_id') ?? 0) === (int) $supplierId);
                        }

                        // Options (notes) - visible when a supplier is selected
                        $sections[] = \Filament\Schemas\Components\Section::make('Options')
                            ->schema([
                                \Filament\Forms\Components\Textarea::make('notes')
                                    ->label('Order Notes')
                                    ->rows(3)
                                    ->placeholder('Optional notes for this supplier order')
                                    ->helperText('These notes will be added to the created purchase order.'),
                            ])
                            ->visible(fn (Get $get): bool => (int) ($get('selected_supplier_id') ?? 0) > 0);

                        return $sections;
                    })
                    ->action(function (array $data): void {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();

                        // Get the default currency
                        /** @var \App\Models\Team|null $team */
                        $team = Filament::getTenant();
                        $defaultCurrencyCode = $team?->getErpSettings()->default_currency ?? 'USD';
                        $defaultCurrencyId = Currency::query()
                            ->where('code', $defaultCurrencyCode)
                            ->where('is_active', true)
                            ->value('id');

                        // Get accepted buyer quotes for notes
                        $acceptedQuotes = BuyerQuote::query()
                            ->where('request_id', $request->getKey())
                            ->where('status', BuyerQuoteStatus::ACCEPTED)
                            ->get();

                        if ($acceptedQuotes->isEmpty()) {
                            Notification::make()
                                ->title('No accepted buyer quote')
                                ->body('There is no accepted buyer quote to create supplier orders from.')
                                ->warning()
                                ->send();

                            return;
                        }

                        // Group quote items by supplier (from all accepted quotes)
                        $itemsBySupplier = $this->groupAcceptedBuyerQuoteItemsBySupplier($request);

                        if ($itemsBySupplier === []) {
                            Notification::make()
                                ->title('No suppliers assigned')
                                ->body('Request items do not have suppliers assigned. Please assign suppliers to items first.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $supplierId = (int) ($data['selected_supplier_id'] ?? 0);
                        if ($supplierId === 0 || ! isset($itemsBySupplier[$supplierId])) {
                            Notification::make()
                                ->title('No supplier selected')
                                ->body('Please select a supplier to create a purchase order for.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $quoteItems = $itemsBySupplier[$supplierId]['items'];
                        $itemsToOrder = BuyerQuoteItem::filterForTotals($quoteItems);

                        if ($itemsToOrder->isEmpty()) {
                            Notification::make()
                                ->title('No items to order')
                                ->body('No orderable items found for the selected supplier.')
                                ->warning()
                                ->send();

                            return;
                        }

                        // Check if a supplier order already exists for this request and supplier
                        $existingOrder = SupplierOrder::query()
                            ->where('request_id', $request->getKey())
                            ->where('supplier_id', $supplierId)
                            ->whereNotIn('status', [OrderStatus::CANCELLED])
                            ->exists();

                        if ($existingOrder) {
                            Notification::make()
                                ->title('Order already exists')
                                ->body('A supplier order already exists for the selected supplier.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $quoteNumbers = $acceptedQuotes->pluck('quote_number')->join(', ');

                        $supplierOrder = new SupplierOrder;
                        $supplierOrder->team_id = $request->team_id;
                        /** @var int|null $creatorId */
                        $creatorId = auth()->id();
                        $supplierOrder->creator_id = $creatorId;
                        $supplierOrder->request_id = $request->getKey();
                        $supplierOrder->supplier_id = $supplierId;
                        $supplierOrder->currency_id = $defaultCurrencyId;
                        $supplierOrder->exchange_rate = '1.00000000';

                        $customNotes = trim($data['notes'] ?? '');
                        if ($customNotes !== '') {
                            $supplierOrder->notes = $customNotes;
                        } else {
                            $supplierOrder->notes = "Created from Accepted Buyer Quote(s): {$quoteNumbers}";
                        }

                        $supplierOrder->save();

                        foreach ($itemsToOrder->values() as $index => $quoteItem) {
                            /** @var BuyerQuoteItem $quoteItem */
                            $this->createSupplierOrderItemFromBuyerQuoteItem($supplierOrder, $quoteItem, $index);
                        }

                        $supplierOrder->recalculateTotals();

                        Notification::make()
                            ->title('Supplier order created')
                            ->body('One purchase order created for the selected supplier. It is in draft status and needs to be confirmed before approval.')
                            ->success()
                            ->send();

                        // Prompt at PO issuance: if suppliers participated via
                        // the portal and outcomes were never announced, remind
                        // staff — suppliers otherwise stay "under review" forever.
                        $hasUnannouncedPortalRound = ! $request->supplierRequestOutcomesAnnounced()
                            && $request->supplierQuotes()
                                ->whereIn('status', [SupplierQuoteStatus::RECEIVED, SupplierQuoteStatus::SELECTED])
                                ->whereNull('declined_at')
                                ->whereNotNull('sent_to_supplier_at')
                                ->exists();

                        if ($hasUnannouncedPortalRound) {
                            Notification::make()
                                ->title('Request outcomes not announced yet')
                                ->body('Suppliers have not been informed of the outcome of this request. Announce outcomes from the supplier quote comparison or the approved Quotation Evaluation.')
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    })
                    ->visible(fn (): bool => BuyerQuote::query()
                        ->where('request_id', $this->getOwnerRecord()->getKey())
                        ->where('status', BuyerQuoteStatus::ACCEPTED)
                        ->exists()),
                Action::make('sendAllToSuppliers')
                    ->label('Send PO to Suppliers')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->size(Size::Small)
                    ->authorize(fn (): bool => $this->sendableSupplierOrders()->contains(
                        fn (SupplierOrder $order): bool => auth()->user()?->can('send', $order) === true,
                    ))
                    ->visible(fn (): bool => $this->sendableSupplierOrders()->isNotEmpty())
                    ->requiresConfirmation()
                    ->modalHeading('Send all approved purchase orders?')
                    ->modalDescription(function (): string {
                        $orders = $this->sendableSupplierOrders();
                        $withoutEmail = $orders->filter(fn (SupplierOrder $order): bool => empty($order->supplier->email))->count();

                        $description = "This will mark {$orders->count()} approved purchase order(s) as sent and email each one to its supplier.";

                        if ($withoutEmail > 0) {
                            $description .= "\n\n⚠️ **Warning:** {$withoutEmail} order(s) have a supplier without an email address. They will be marked as sent, but no email will be sent for them.";
                        }

                        return $description;
                    })
                    ->action(function (): void {
                        $orders = $this->sendableSupplierOrders()
                            ->filter(fn (SupplierOrder $order): bool => auth()->user()?->can('send', $order) === true);

                        $sender = app(SendSupplierOrderToSupplier::class);
                        $sent = 0;
                        $markedWithoutEmail = 0;
                        $failed = 0;

                        foreach ($orders as $order) {
                            match ($sender->execute($order)) {
                                SupplierOrderSendOutcome::Sent => $sent++,
                                SupplierOrderSendOutcome::MarkedWithoutEmail => $markedWithoutEmail++,
                                SupplierOrderSendOutcome::EmailFailed => $failed++,
                            };
                        }

                        $body = "{$sent} purchase order(s) emailed to suppliers.";
                        if ($markedWithoutEmail > 0) {
                            $body .= " {$markedWithoutEmail} marked as sent without email (no supplier address).";
                        }
                        if ($failed > 0) {
                            $body .= " {$failed} marked as sent but the email failed to send.";
                        }

                        Notification::make()
                            ->title('Purchase orders sent')
                            ->body($body)
                            ->color($failed > 0 ? 'warning' : 'success')
                            ->send();
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn (SupplierOrder $record): bool => $record->is_editable),
                    Action::make('confirm')
                        ->label('Confirm')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (SupplierOrder $record): bool => $record->status->canConfirm() && $record->status !== OrderStatus::SENT)
                        ->requiresConfirmation()
                        ->modalHeading('Confirm this order?')
                        ->modalDescription('This will confirm the purchase order, lock it for editing, and notify approvers.')
                        ->action(function (SupplierOrder $record): void {
                            $record->confirm();
                            Notification::make()
                                ->title('Order confirmed')
                                ->body('Approval request emails have been sent to eligible approvers.')
                                ->success()
                                ->send();
                        }),
                    ApproveSupplierOrderAction::make(),
                    DownloadPdfAction::make('downloadPdfSent')
                        ->label('PDF')
                        ->visible(fn (SupplierOrder $record): bool => $record->status === OrderStatus::SENT),
                    DownloadPdfAction::make('downloadPdfApproved')
                        ->label('PDF')
                        ->visible(fn (?SupplierOrder $record): bool => $record !== null && $record->status === OrderStatus::APPROVED),
                    Action::make('send')
                        ->label('Send Purchase Order')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->authorize(fn (?SupplierOrder $record): bool => $record !== null && auth()->user()?->can('send', $record) === true)
                        ->visible(fn (?SupplierOrder $record): bool => $record !== null && $record->status === OrderStatus::APPROVED)
                        ->requiresConfirmation()
                        ->modalHeading('Send purchase order email to supplier?')
                        ->modalDescription(function (SupplierOrder $record): string {
                            $supplierEmail = $record->supplier->email ?? null;
                            $supplierName = $record->supplier->name ?? 'Unknown';
                            $description = 'This will mark the order as sent and send the purchase order email to the supplier.';

                            if (empty($supplierEmail)) {
                                $description .= "\n\n⚠️ **Warning:** The supplier ({$supplierName}) does not have an email address configured. The order will be marked as sent, but no email will be sent.";
                            } else {
                                $description .= "\n\n📧 Email will be sent to: {$supplierEmail}";
                            }

                            return $description;
                        })
                        ->action(function (SupplierOrder $record): void {
                            $supplierEmail = $record->supplier->email ?? null;
                            $supplierName = $record->supplier->name ?? 'Supplier';

                            $outcome = app(SendSupplierOrderToSupplier::class)->execute($record);

                            match ($outcome) {
                                SupplierOrderSendOutcome::Sent => Notification::make()
                                    ->title('Order sent')
                                    ->body("Purchase order has been sent successfully to {$supplierEmail}.")
                                    ->success()
                                    ->send(),
                                SupplierOrderSendOutcome::MarkedWithoutEmail => Notification::make()
                                    ->title('Order marked as sent')
                                    ->body("Order has been marked as sent, but no email was sent because the supplier ({$supplierName}) does not have an email address configured.")
                                    ->warning()
                                    ->send(),
                                SupplierOrderSendOutcome::EmailFailed => Notification::make()
                                    ->title('Failed to send email')
                                    ->body("Order has been marked as sent, but the email could not be sent to {$supplierEmail}.")
                                    ->danger()
                                    ->send(),
                            };
                        }),
                    Action::make('resend')
                        ->label('Resend')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->authorize(fn (?SupplierOrder $record): bool => $record !== null && auth()->user()?->can('send', $record) === true)
                        ->visible(fn (?SupplierOrder $record): bool => $record !== null && $record->status === OrderStatus::SENT)
                        ->requiresConfirmation()
                        ->modalHeading('Resend purchase order email?')
                        ->modalDescription(function (SupplierOrder $record): string {
                            $supplierEmail = $record->supplier->email ?? null;
                            $supplierName = $record->supplier->name ?? 'Unknown';
                            $description = 'This will resend the purchase order email to the supplier without changing the order status.';

                            if (empty($supplierEmail)) {
                                $description .= "\n\n⚠️ **Warning:** The supplier ({$supplierName}) does not have an email address configured. No email will be sent.";
                            } else {
                                $description .= "\n\n📧 Email will be sent to: {$supplierEmail}";
                            }

                            return $description;
                        })
                        ->action(function (SupplierOrder $record): void {
                            // Resend email to supplier (without changing status)
                            $supplierEmail = $record->supplier->email ?? null;
                            $supplierName = $record->supplier->name ?? 'Supplier';

                            if (empty($supplierEmail)) {
                                Notification::make()
                                    ->title('Cannot resend email')
                                    ->body("The supplier ({$supplierName}) does not have an email address configured.")
                                    ->warning()
                                    ->send();

                                return;
                            }

                            try {
                                $emailService = app(EmailTemplateService::class);
                                $settings = $record->team->getErpSettings();
                                $emailService->sendWithTeamSettings(
                                    $record->team,
                                    new PurchaseOrderToSupplierMail($record),
                                    $supplierEmail,
                                    $settings->email_template_supplier_order
                                );

                                Notification::make()
                                    ->title('Email resent')
                                    ->body("Purchase order email has been resent successfully to {$supplierEmail}.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Log::error('Failed to resend purchase order email', [
                                    'order_id' => $record->id,
                                    'supplier_email' => $supplierEmail,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);

                                Notification::make()
                                    ->title('Failed to resend email')
                                    ->body("The email could not be sent to {$supplierEmail}. Error: ".$e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->authorize(fn (?SupplierOrder $record): bool => $record !== null && auth()->user()?->can('cancel', $record) === true)
                        ->visible(fn (SupplierOrder $record): bool => $record->is_cancellable)
                        ->requiresConfirmation()
                        ->modalHeading('Cancel this order?')
                        ->modalDescription('This action cannot be undone.')
                        ->action(function (SupplierOrder $record): void {
                            $record->cancel();
                            Notification::make()
                                ->title('Order cancelled')
                                ->warning()
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
            $unitPriceExcTax = $unitPrice / (1 + $taxRate / 100);
            $taxAmount = $unitPrice - $unitPriceExcTax;
        } else {
            $lineTax = $lineAmount * $taxRate / 100;
            $lineTotal = $lineAmount + $lineTax;
            $unitPriceExcTax = $unitPrice;
            $taxAmount = $unitPrice * $taxRate / 100;
        }

        $set('unit_price_exc_tax', round($unitPriceExcTax, 4));
        $set('tax_amount', round($taxAmount, 4));
        $set('line_total', round($lineTotal, 4));
    }

    /**
     * Check if the supplier selected in the form is taxable.
     */
    private function isSupplierTaxable(Get $get): bool
    {
        // Get supplier_id from the parent form (go up from repeater item to form level)
        $supplierId = $get('../../supplier_id');

        if ($supplierId === null) {
            // When editing existing order, check the record's supplier
            $record = $this->getMountedTableActionRecord();
            if ($record instanceof SupplierOrder && $record->supplier_id !== null) {
                $supplierId = $record->supplier_id;
            } else {
                return true; // Default to showing tax fields if no supplier selected
            }
        }

        $supplier = Company::query()->find(SafeCast::toInt($supplierId));

        return $supplier->is_taxable ?? true;
    }

    /**
     * Check if the supplier in an existing record is taxable.
     */
    private function isRecordSupplierTaxable(?SupplierOrder $record): bool
    {
        if (! $record instanceof \App\Models\SupplierOrder || $record->supplier_id === null) {
            return true; // Default to showing tax fields
        }

        $supplier = Company::query()->find($record->supplier_id);

        return $supplier->is_taxable ?? true;
    }

    /**
     * Approved supplier orders for this request that are ready to be sent to
     * their suppliers (not yet sent, not cancelled).
     *
     * @return \Illuminate\Support\Collection<int, SupplierOrder>
     */
    private function sendableSupplierOrders(): \Illuminate\Support\Collection
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $request->supplierOrders()
            ->where('status', OrderStatus::APPROVED)
            ->with('supplier')
            ->get();
    }

    /**
     * @return array<int, array{supplier: Company, items: \Illuminate\Support\Collection<int, BuyerQuoteItem>}>
     */
    private function groupAcceptedBuyerQuoteItemsBySupplier(Request $request): array
    {
        $acceptedQuotes = BuyerQuote::query()
            ->where('request_id', $request->getKey())
            ->where('status', BuyerQuoteStatus::ACCEPTED)
            ->with(['items.requestItem.supplier', 'items.supplierQuoteItem.supplierQuote.supplier', 'items.unitOfMeasure', 'items.article'])
            ->get();

        $itemsBySupplier = [];

        foreach ($acceptedQuotes as $quote) {
            foreach ($quote->items as $quoteItem) {
                if ($quoteItem->request_item_id === null) {
                    continue;
                }

                $supplierId = $this->resolveBuyerQuoteItemSupplierId($quoteItem);
                if ($supplierId === null) {
                    continue;
                }

                if (! isset($itemsBySupplier[$supplierId])) {
                    $supplier = $quoteItem->requestItem->supplier
                        ?? $quoteItem->supplierQuoteItem?->supplierQuote?->supplier;

                    if ($supplier === null) {
                        continue;
                    }

                    $itemsBySupplier[$supplierId] = [
                        'supplier' => $supplier,
                        'items' => collect(),
                    ];
                }

                $itemsBySupplier[$supplierId]['items']->push($quoteItem);
            }
        }

        return $itemsBySupplier;
    }

    private function resolveBuyerQuoteItemSupplierId(BuyerQuoteItem $quoteItem): ?int
    {
        $requestItem = $quoteItem->requestItem;

        if ($requestItem !== null && $requestItem->supplier_id !== null) {
            return (int) $requestItem->supplier_id;
        }

        if ($quoteItem->supplierQuoteItem?->supplierQuote?->supplier !== null) {
            return (int) $quoteItem->supplierQuoteItem->supplierQuote->supplier->getKey();
        }

        return null;
    }

    private function getBuyerQuoteItemCostUnitPrice(BuyerQuoteItem $quoteItem): float
    {
        return $quoteItem->supplierQuoteItem !== null
            ? (float) ($quoteItem->supplierQuoteItem->unit_price ?? 0)
            : (float) ($quoteItem->cost_price ?? 0);
    }

    private function getBuyerQuoteItemCostLineTotal(BuyerQuoteItem $quoteItem): float
    {
        return $this->getBuyerQuoteItemCostUnitPrice($quoteItem) * (float) $quoteItem->quantity;
    }

    private function getBuyerQuoteItemUnitLabel(BuyerQuoteItem $quoteItem): string
    {
        if ($quoteItem->unitOfMeasure !== null) {
            return $quoteItem->unitOfMeasure->code;
        }

        $quoteUnit = $quoteItem->unit;

        return $quoteUnit instanceof \App\Enums\Unit ? $quoteUnit->value : (string) ($quoteUnit ?? 'pcs');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, BuyerQuoteItem>  $quoteItems
     */
    private function buildBuyerQuoteItemsPreviewHtml(\Illuminate\Support\Collection $quoteItems): string
    {
        $itemsForTotal = BuyerQuoteItem::filterForTotals($quoteItems);
        $supplierTotal = $itemsForTotal->sum(fn (BuyerQuoteItem $item): float => $this->getBuyerQuoteItemCostLineTotal($item));
        $organizedItems = BuyerQuoteItem::organizeHierarchically($quoteItems);

        $html = '<div class="space-y-1 text-sm">';

        foreach ($organizedItems as $entry) {
            /** @var BuyerQuoteItem $item */
            $item = $entry['item'];
            $isChild = $entry['is_child'];
            $costPrice = $this->getBuyerQuoteItemCostUnitPrice($item);
            $lineTotal = $this->getBuyerQuoteItemCostLineTotal($item);
            $unit = $this->getBuyerQuoteItemUnitLabel($item);

            $label = $item->article !== null
                ? '['.$item->article->code.'] '.$item->article->name
                : $item->description;

            $rowClass = $isChild
                ? 'flex justify-between gap-4 pl-4 text-gray-500 dark:text-gray-400 text-xs'
                : 'flex justify-between gap-4 font-semibold bg-primary-50/80 dark:bg-primary-950/40 border-l-[3px] border-primary-500 px-2 py-1 rounded-r text-gray-900 dark:text-gray-100';

            $prefix = $isChild ? '↳ ' : '• ';

            $html .= sprintf(
                '<div class="%s"><span>%s%s</span><span class="whitespace-nowrap">%s %s × %s = <strong>%s</strong></span></div>',
                $rowClass,
                $prefix,
                e($label),
                number_format((float) $item->quantity, 2),
                e($unit),
                number_format($costPrice, 2),
                number_format($lineTotal, 2)
            );
        }

        $html .= '</div>';
        $html .= sprintf(
            '<div class="mt-2 pt-2 border-t-2 border-primary-300 dark:border-primary-600 bg-primary-50/50 dark:bg-primary-950/30 -mx-1 px-2 py-2 rounded text-right"><span class="font-semibold text-primary-800 dark:text-primary-200">Supplier Total</span>%s<br><span class="text-base font-bold text-primary-700 dark:text-primary-300">%s</span></div>',
            $quoteItems->count() > $itemsForTotal->count()
                ? '<br><span class="text-xs font-normal text-primary-600/80 dark:text-primary-400/80">(main items)</span>'
                : '',
            number_format($supplierTotal, 2)
        );

        return $html;
    }

    private function createSupplierOrderItemFromBuyerQuoteItem(SupplierOrder $supplierOrder, BuyerQuoteItem $quoteItem, int $sortOrder): void
    {
        $costPrice = $this->getBuyerQuoteItemCostUnitPrice($quoteItem);
        $unitCode = $this->getBuyerQuoteItemUnitLabel($quoteItem);

        $item = new SupplierOrderItem([
            'supplier_order_id' => $supplierOrder->getKey(),
            'request_item_id' => $quoteItem->request_item_id,
            'article_id' => $quoteItem->article_id,
            'description' => $quoteItem->description,
            'quantity' => $quoteItem->quantity,
            'unit_of_measure_id' => $quoteItem->unit_of_measure_id,
            'unit_price' => (string) $costPrice,
            'unit_price_exc_tax' => (string) $costPrice,
            'tax_amount' => '0.0000',
            'line_total' => (string) round((float) $quoteItem->quantity * $costPrice, 4),
            'tax_rate' => '0.0000',
            'sort_order' => $sortOrder,
            'notes' => $quoteItem->notes,
        ]);

        $attributes = $item->getAttributes();
        $item->setRawAttributes(array_merge($attributes, ['unit' => $unitCode]));
        $item->save();
    }
}
