<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\OrderStatus;
use App\Enums\RequestStage;
use App\Enums\SupplierQuoteStatus;
use App\Filament\Actions\DownloadPdfAction;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Mail\Erp\PurchaseOrderToSupplierMail;
use App\Models\BuyerOrder;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\SupplierQuote;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Services\Email\EmailTemplateService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Illuminate\Support\Facades\Log;
use Filament\Facades\Filament;
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
        return 'Purchases';
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
                                    ->searchable()
                                    ->required()
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
                                    ->searchable()
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
                                    ->options(OrderStatus::class)
                                    ->default(OrderStatus::DRAFT)
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
                                        /** @var \App\Models\Team|null $team */
                                        $team = Filament::getTenant();
                                        $defaultCode = $team?->getErpSettings()->default_currency ?? 'USD';

                                        return Currency::query()->where('code', $defaultCode)->where('is_active', true)->value('id');
                                    })
                                    ->searchable()
                                    ->required(),
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
                                            ->searchable()
                                            ->preload()
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
                                            ->searchable()
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
                                            ->visible(fn (Get $get): bool => $this->isSupplierTaxable($get)),
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
                                    ->content(fn (?SupplierOrder $record): string => $record instanceof \App\Models\SupplierOrder
                                        ? ($record->currency?->formatNumber((float) $record->subtotal) ?? number_format((float) $record->subtotal, 2))
                                        : '0,-'),
                                Placeholder::make('tax_total_display')
                                    ->label('Tax Total')
                                    ->content(fn (?SupplierOrder $record): string => $record instanceof \App\Models\SupplierOrder
                                        ? ($record->currency?->formatNumber((float) $record->tax_total) ?? number_format((float) $record->tax_total, 2))
                                        : '0,-')
                                    ->visible(fn (?SupplierOrder $record): bool => ! $record instanceof \App\Models\SupplierOrder || $this->isRecordSupplierTaxable($record)),
                                Placeholder::make('total_display')
                                    ->label('Total')
                                    ->content(fn (?SupplierOrder $record): string => $record instanceof \App\Models\SupplierOrder
                                        ? ($record->currency?->format((float) $record->total) ?? number_format((float) $record->total, 2))
                                        : '0,-'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('base_subtotal_display')
                                    ->label('Subtotal (Base)')
                                    ->content(function (?SupplierOrder $record): string {
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
                                    ->content(function (?SupplierOrder $record): string {
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
                                    ->visible(fn (?SupplierOrder $record): bool => ! $record instanceof \App\Models\SupplierOrder || $this->isRecordSupplierTaxable($record)),
                                Placeholder::make('base_total_display')
                                    ->label('Total (Base)')
                                    ->content(function (?SupplierOrder $record): string {
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplierQuote.quote_number')
                    ->label('From Quote')
                    ->placeholder('Direct Order')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
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
                    ->options(OrderStatus::class),
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
                    ->label('Create from Buyer Order')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('info')
                    ->size(Size::Small)
                    ->modalHeading('Create Supplier Orders from Buyer Order')
                    ->modalDescription('Review the supplier orders that will be created:')
                    ->modalWidth('4xl')
                    ->form(function (): array {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();

                        // Get confirmed buyer order
                        /** @var BuyerOrder|null $buyerOrder */
                        $buyerOrder = BuyerOrder::query()
                            ->where('request_id', $request->getKey())
                            ->where('status', OrderStatus::CONFIRMED)
                            ->with(['items.buyerQuoteItem.supplierQuoteItem.supplierQuote.supplier', 'items.requestItem.supplier', 'items.unitOfMeasure'])
                            ->first();

                        if ($buyerOrder === null) {
                            return [
                                Placeholder::make('no_order')
                                    ->label('')
                                    ->content('No confirmed buyer order available.'),
                            ];
                        }

                        // Group items by supplier
                        $itemsBySupplier = [];
                        foreach ($buyerOrder->items as $buyerOrderItem) {
                            /** @var \App\Models\BuyerOrderItem $buyerOrderItem */
                            $requestItem = $buyerOrderItem->requestItem;
                            
                            // Try to get supplier from request item first
                            $supplier = null;
                            $supplierId = null;
                            
                            if ($requestItem !== null && $requestItem->supplier_id !== null) {
                                $supplier = $requestItem->supplier;
                                $supplierId = $requestItem->supplier_id;
                            } elseif ($buyerOrderItem->buyerQuoteItem?->supplierQuoteItem?->supplierQuote?->supplier !== null) {
                                // Fallback to supplier from quote chain
                                $supplier = $buyerOrderItem->buyerQuoteItem->supplierQuoteItem->supplierQuote->supplier;
                                $supplierId = $supplier->getKey();
                            }
                            
                            if ($supplierId === null || $supplier === null) {
                                continue;
                            }
                            if (! isset($itemsBySupplier[$supplierId])) {
                                $itemsBySupplier[$supplierId] = [
                                    'supplier' => $supplier,
                                    'items' => [],
                                    'total' => 0,
                                ];
                            }

                            // Get cost price
                            $costPrice = 0;
                            if ($buyerOrderItem->buyerQuoteItem?->supplierQuoteItem !== null) {
                                $costPrice = (float) ($buyerOrderItem->buyerQuoteItem->supplierQuoteItem->unit_price ?? 0);
                            } elseif ($buyerOrderItem->buyerQuoteItem !== null) {
                                $costPrice = (float) ($buyerOrderItem->buyerQuoteItem->cost_price ?? 0);
                            }

                            $lineTotal = $costPrice * (float) $buyerOrderItem->quantity;

                            $itemsBySupplier[$supplierId]['items'][] = [
                                'description' => $buyerOrderItem->description,
                                'quantity' => $buyerOrderItem->quantity,
                                'unit_of_measure_id' => $buyerOrderItem->unit_of_measure_id,
                                'unit' => $buyerOrderItem->unitOfMeasure?->code ?? $buyerOrderItem->unit?->value ?? 'pcs',
                                'unit_price' => $costPrice,
                                'line_total' => $lineTotal,
                            ];
                            $itemsBySupplier[$supplierId]['total'] += $lineTotal;
                        }

                        if ($itemsBySupplier === []) {
                            return [
                                Placeholder::make('no_suppliers')
                                    ->label('')
                                    ->content('No items with assigned suppliers found. Please assign suppliers to request items first.'),
                            ];
                        }

                        // Build form sections for each supplier
                        $sections = [];
                        $grandTotal = 0;

                        foreach ($itemsBySupplier as $supplierId => $data) {
                            /** @var Company $supplier */
                            $supplier = $data['supplier'];
                            $items = $data['items'];
                            $supplierTotal = $data['total'];
                            $grandTotal += $supplierTotal;

                            // Check if order already exists
                            $existingOrder = SupplierOrder::query()
                                ->where('request_id', $request->getKey())
                                ->where('supplier_id', $supplierId)
                                ->whereNotIn('status', [OrderStatus::CANCELLED])
                                ->exists();

                            $statusBadge = $existingOrder ? ' ⚠️ (Order exists - will skip)' : '';

                            // Build items table as HTML
                            $itemsHtml = '<div class="space-y-1 text-sm">';
                            foreach ($items as $item) {
                                $itemsHtml .= sprintf(
                                    '<div class="flex justify-between"><span>• %s</span><span class="text-gray-500">%s %s × %s = <strong>%s</strong></span></div>',
                                    e($item['description']),
                                    number_format((float) $item['quantity'], 2),
                                    e($item['unit']),
                                    number_format($item['unit_price'], 2),
                                    number_format($item['line_total'], 2)
                                );
                            }
                            $itemsHtml .= sprintf(
                                '</div><div class="mt-2 pt-2 border-t text-right font-semibold">Supplier Total: %s</div>',
                                number_format($supplierTotal, 2)
                            );

                            $sections[] = \Filament\Schemas\Components\Section::make("[{$supplier->code}] {$supplier->name}{$statusBadge}")
                                ->description(count($items).' item(s)')
                                ->schema([
                                    Placeholder::make("items_{$supplierId}")
                                        ->label('')
                                        ->content(new \Illuminate\Support\HtmlString($itemsHtml)),
                                ])
                                ->collapsible()
                                ->collapsed(count($itemsBySupplier) > 3);
                        }

                        // Add summary section with options
                        $summaryHtml = sprintf(
                            '<div class="grid grid-cols-2 gap-4 text-sm"><div><span class="text-gray-500">Total Suppliers:</span> <strong>%d</strong></div><div><span class="text-gray-500">Total Cost:</span> <strong>%s</strong></div></div>',
                            count($itemsBySupplier),
                            number_format($grandTotal, 2)
                        );

                        $sections[] = \Filament\Schemas\Components\Section::make('Summary')
                            ->schema([
                                Placeholder::make('summary')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString($summaryHtml)),
                            ]);

                        // Add options section
                        $sections[] = \Filament\Schemas\Components\Section::make('Options')
                            ->schema([
                                \Filament\Forms\Components\Textarea::make('notes')
                                    ->label('Order Notes')
                                    ->rows(3)
                                    ->placeholder('Optional notes for the supplier orders')
                                    ->helperText('These notes will be added to all created supplier orders'),
                                \Filament\Forms\Components\Checkbox::make('confirm_and_send')
                                    ->label('Confirm and send to suppliers')
                                    ->helperText('Confirm orders and send PO emails to suppliers'),
                            ]);

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

                        // Get confirmed buyer order
                        /** @var BuyerOrder|null $buyerOrder */
                        $buyerOrder = BuyerOrder::query()
                            ->where('request_id', $request->getKey())
                            ->where('status', OrderStatus::CONFIRMED)
                            ->with(['items.buyerQuoteItem.supplierQuoteItem.supplierQuote.supplier', 'items.requestItem.supplier'])
                            ->first();

                        if ($buyerOrder === null) {
                            Notification::make()
                                ->title('No confirmed buyer order')
                                ->body('There is no confirmed buyer order to create supplier orders from.')
                                ->warning()
                                ->send();

                            return;
                        }

                        // Group items by supplier from request items or quote chain
                        $itemsBySupplier = [];
                        foreach ($buyerOrder->items as $buyerOrderItem) {
                            /** @var \App\Models\BuyerOrderItem $buyerOrderItem */
                            $requestItem = $buyerOrderItem->requestItem;
                            
                            // Try to get supplier from request item first
                            $supplierId = null;
                            
                            if ($requestItem !== null && $requestItem->supplier_id !== null) {
                                $supplierId = $requestItem->supplier_id;
                            } elseif ($buyerOrderItem->buyerQuoteItem?->supplierQuoteItem?->supplierQuote?->supplier !== null) {
                                // Fallback to supplier from quote chain
                                $supplierId = $buyerOrderItem->buyerQuoteItem->supplierQuoteItem->supplierQuote->supplier->getKey();
                            }
                            
                            if ($supplierId === null) {
                                continue;
                            }
                            if (! isset($itemsBySupplier[$supplierId])) {
                                $itemsBySupplier[$supplierId] = [];
                            }
                            $itemsBySupplier[$supplierId][] = $buyerOrderItem;
                        }

                        if ($itemsBySupplier === []) {
                            Notification::make()
                                ->title('No suppliers assigned')
                                ->body('Request items do not have suppliers assigned. Please assign suppliers to items first.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $ordersCreated = 0;
                        $emailsSent = 0;
                        $emailsFailed = 0;
                        $noEmailAddress = 0;

                        // Create a supplier order for each supplier
                        foreach ($itemsBySupplier as $supplierId => $items) {
                            // Check if a supplier order already exists for this request and supplier
                            $existingOrder = SupplierOrder::query()
                                ->where('request_id', $request->getKey())
                                ->where('supplier_id', $supplierId)
                                ->whereNotIn('status', [OrderStatus::CANCELLED])
                                ->exists();

                            if ($existingOrder) {
                                continue; // Skip if order already exists
                            }

                            // Create the supplier order
                            $supplierOrder = new SupplierOrder;
                            $supplierOrder->team_id = $request->team_id;
                            /** @var int|null $creatorId */
                            $creatorId = auth()->id();
                            $supplierOrder->creator_id = $creatorId;
                            $supplierOrder->request_id = $request->getKey();
                            $supplierOrder->supplier_id = $supplierId;
                            $supplierOrder->currency_id = $defaultCurrencyId;
                            $supplierOrder->exchange_rate = '1.00000000';
                            
                            // Set notes: use custom notes if provided, otherwise use default message
                            $customNotes = trim($data['notes'] ?? '');
                            if (!empty($customNotes)) {
                                $supplierOrder->notes = $customNotes;
                            } else {
                                $supplierOrder->notes = "Created from Buyer Order #{$buyerOrder->order_number}";
                            }
                            
                            $supplierOrder->save();

                            // Add items for this supplier
                            foreach ($items as $index => $buyerOrderItem) {
                                /** @var \App\Models\BuyerOrderItem $buyerOrderItem */
                                // Try to get cost price from supplier quote item chain
                                $costPrice = '0.0000';
                                if ($buyerOrderItem->buyerQuoteItem?->supplierQuoteItem !== null) {
                                    $costPrice = $buyerOrderItem->buyerQuoteItem->supplierQuoteItem->unit_price ?? '0.0000';
                                } elseif ($buyerOrderItem->buyerQuoteItem !== null) {
                                    $costPrice = $buyerOrderItem->buyerQuoteItem->cost_price ?? '0.0000';
                                }

                                // Ensure unit is set from unit_of_measure_id, bypassing SafeUnitCast
                                $unitCode = 'pcs'; // Default fallback
                                if ($buyerOrderItem->unit_of_measure_id !== null) {
                                    $unitOfMeasure = \App\Models\UnitOfMeasure::find($buyerOrderItem->unit_of_measure_id);
                                    if ($unitOfMeasure !== null) {
                                        $unitCode = $unitOfMeasure->code;
                                    } else {
                                        // Fallback to buyer order item's unit
                                        $orderUnit = $buyerOrderItem->unit;
                                        $unitCode = $orderUnit instanceof \App\Enums\Unit ? $orderUnit->value : ($orderUnit ?? 'pcs');
                                    }
                                } else {
                                    // Fallback to buyer order item's unit
                                    $orderUnit = $buyerOrderItem->unit;
                                    $unitCode = $orderUnit instanceof \App\Enums\Unit ? $orderUnit->value : ($orderUnit ?? 'pcs');
                                }
                                
                                $item = SupplierOrderItem::make([
                                    'supplier_order_id' => $supplierOrder->getKey(),
                                    'request_item_id' => $buyerOrderItem->request_item_id,
                                    'article_id' => $buyerOrderItem->article_id,
                                    'description' => $buyerOrderItem->description,
                                    'quantity' => $buyerOrderItem->quantity,
                                    'unit_of_measure_id' => $buyerOrderItem->unit_of_measure_id,
                                    'unit_price' => $costPrice,
                                    'unit_price_exc_tax' => $costPrice,
                                    'tax_amount' => '0.0000',
                                    'line_total' => (string) ((float) $buyerOrderItem->quantity * (float) $costPrice),
                                    'tax_rate' => '0.0000',
                                    'sort_order' => $index,
                                    'notes' => $buyerOrderItem->notes,
                                ]);
                                
                                // Use setRawAttributes to bypass SafeUnitCast and ensure unit is set
                                $attributes = $item->getAttributes();
                                $item->setRawAttributes(array_merge($attributes, ['unit' => (string) $unitCode]));
                                $item->save();
                            }

                            // Recalculate totals
                            $supplierOrder->recalculateTotals();

                            // Confirm and send if requested
                            if ($data['confirm_and_send'] ?? false) {
                                $supplierOrder->confirm();
                                $supplierOrder->markAsOrdered();

                                // Send email to supplier
                                $supplierEmail = $supplierOrder->supplier->email ?? null;
                                $supplierName = $supplierOrder->supplier->name ?? 'Supplier';
                                
                                if (empty($supplierEmail)) {
                                    $noEmailAddress++;
                                    // Log warning but don't fail the operation
                                    \Illuminate\Support\Facades\Log::warning('Cannot send purchase order email - supplier has no email', [
                                        'order_id' => $supplierOrder->id,
                                        'supplier_id' => $supplierOrder->supplier_id,
                                        'supplier_name' => $supplierName,
                                    ]);
                                } else {
                                    try {
                                        $emailService = app(EmailTemplateService::class);
                                        $settings = $supplierOrder->team->getErpSettings();
                                        $emailService->sendWithTeamSettings(
                                            $supplierOrder->team,
                                            new PurchaseOrderToSupplierMail($supplierOrder),
                                            $supplierEmail,
                                            $settings->email_template_supplier_order, // Old system fallback
                                            $settings->email_template_supplier_order_id ?? null, // New system
                                            \App\Models\EmailTemplate::TYPE_SUPPLIER_ORDER
                                        );
                                        $emailsSent++;
                                    } catch (\Exception $e) {
                                        $emailsFailed++;
                                        // Log error but don't fail the operation
                                        \Illuminate\Support\Facades\Log::error('Failed to send purchase order email', [
                                            'order_id' => $supplierOrder->id,
                                            'supplier_email' => $supplierEmail,
                                            'error' => $e->getMessage(),
                                            'trace' => $e->getTraceAsString(),
                                        ]);
                                    }
                                }
                            }

                            $ordersCreated++;
                        }

                        if ($ordersCreated > 0) {
                            $notification = Notification::make()
                                ->title('Supplier orders created')
                                ->success();

                            $bodyParts = ["{$ordersCreated} purchase order(s) created from Buyer Order #{$buyerOrder->order_number}"];
                            
                            if ($data['confirm_and_send'] ?? false) {
                                if ($emailsSent > 0) {
                                    $bodyParts[] = "{$emailsSent} email(s) sent successfully";
                                }
                                if ($noEmailAddress > 0) {
                                    $bodyParts[] = "{$noEmailAddress} order(s) could not be sent (supplier has no email address)";
                                    $notification->warning();
                                }
                                if ($emailsFailed > 0) {
                                    $bodyParts[] = "{$emailsFailed} email(s) failed to send (check logs for details)";
                                    $notification->warning();
                                }
                            }

                            $notification->body(implode('. ', $bodyParts))->send();
                        } else {
                            Notification::make()
                                ->title('No new orders created')
                                ->body('Supplier orders already exist for all assigned suppliers.')
                                ->info()
                                ->send();
                        }
                    })
                    ->visible(fn (): bool => BuyerOrder::query()
                        ->where('request_id', $this->getOwnerRecord()->getKey())
                        ->where('status', OrderStatus::CONFIRMED)
                        ->exists()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn (SupplierOrder $record): bool => $record->is_editable),
                    DownloadPdfAction::make()
                        ->label('PDF'),
                    Action::make('confirm')
                        ->label('Confirm')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (SupplierOrder $record): bool => $record->status->canConfirm())
                        ->requiresConfirmation()
                        ->modalHeading('Confirm this order?')
                        ->modalDescription('This will confirm the purchase order and lock it for editing.')
                        ->action(function (SupplierOrder $record): void {
                            $record->confirm();
                            Notification::make()
                                ->title('Order confirmed')
                                ->success()
                                ->send();
                        }),
                    Action::make('send')
                        ->label('Send')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->visible(fn (?SupplierOrder $record): bool => $record !== null && $record->status->canSend())
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
                            // Mark as sent first
                            $record->markAsSent();

                            // Send email to supplier
                            $supplierEmail = $record->supplier->email ?? null;
                            $supplierName = $record->supplier->name ?? 'Supplier';
                            
                            if (empty($supplierEmail)) {
                            Notification::make()
                                ->title('Order marked as sent')
                                    ->body("Order has been marked as sent, but no email was sent because the supplier ({$supplierName}) does not have an email address configured.")
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
                                    ->title('Order sent')
                                    ->body("Purchase order has been sent successfully to {$supplierEmail}.")
                                ->success()
                                ->send();
                            } catch (\Exception $e) {
                                Log::error('Failed to send purchase order email', [
                                    'order_id' => $record->id,
                                    'supplier_email' => $supplierEmail,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);

                                Notification::make()
                                    ->title('Failed to send email')
                                    ->body("Order has been marked as sent, but the email could not be sent to {$supplierEmail}. Error: ".$e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('resend')
                        ->label('Resend')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
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

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Request $ownerRecord */
        $hasConfirmed = $ownerRecord->supplierOrders()
            ->whereNotIn('status', [OrderStatus::DRAFT, OrderStatus::CANCELLED])
            ->exists();

        return $hasConfirmed ? '✓' : null;
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
            $record = $this->getMountedTableActionRecord() ?? $this->getRecord();
            if ($record instanceof SupplierOrder && $record->supplier_id !== null) {
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
    private function isRecordSupplierTaxable(?SupplierOrder $record): bool
    {
        if (! $record instanceof \App\Models\SupplierOrder || $record->supplier_id === null) {
            return true; // Default to showing tax fields
        }

        /** @var Company|null $supplier */
        $supplier = Company::query()->find($record->supplier_id);

        return $supplier?->is_taxable ?? true;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Request $ownerRecord */
        $hasConfirmed = $ownerRecord->supplierOrders()
            ->whereNotIn('status', [OrderStatus::DRAFT, OrderStatus::CANCELLED])
            ->exists();

        return $hasConfirmed ? 'success' : null;
    }
}
