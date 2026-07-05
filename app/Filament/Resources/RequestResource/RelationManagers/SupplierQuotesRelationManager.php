<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Actions\Media\AttachUploadedFiles;
use App\Enums\PrepaymentType;
use App\Enums\RequestStage;
use App\Enums\SupplierQuoteStatus;
use App\Filament\Resources\QuotationEvaluationResource;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Support\SafeCast;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
use Illuminate\Contracts\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;

final class SupplierQuotesRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'supplierQuotes';

    /**
     * @var array<int|string, array<int, array<string, mixed>>>|null
     */
    protected ?array $storedChildItemsData = null;

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
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('valid_until')
                                    ->label('Valid Until')
                                    ->helperText('Leave empty for default validity period'),
                                Checkbox::make('obtained')
                                    ->label('Obtained')
                                    ->helperText('When checked, saving with item prices will set status to Selected and allow proceeding to Buyer Quotes without QE.'),
                            ]),
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
                            ->live()
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('due_days')
                                            ->label('Due Days')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->minValue(0)
                                            ->suffix('days')
                                            ->live(),
                                        TextInput::make('percentage')
                                            ->label('Percentage')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->live(),
                                        TextInput::make('job_progress')
                                            ->label('Job Progress (%)')
                                            ->numeric()
                                            ->default(null)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->visible(fn (): bool => $request->hasJobProgress())
                                            ->live(),
                                    ]),
                            ])
                            ->defaultItems(1)
                            ->itemLabel(function (array $state) use ($request): ?string {
                                if (! isset($state['due_days'], $state['percentage'])) {
                                    return null;
                                }
                                $label = "{$state['due_days']} days - {$state['percentage']}%";
                                if ($request->hasJobProgress() && isset($state['job_progress']) && $state['job_progress'] !== '') {
                                    $label .= " - {$state['job_progress']}%";
                                }

                                return $label;
                            })
                            ->addActionLabel('Add Payment Terms')
                            ->reorderableWithButtons()
                            ->collapsible(),
                    ])
                    ->collapsible(),

                Section::make('Line Items')
                    ->schema([
                        (function () use ($request) {
                            $relationManager = $this; // Capture RelationManager instance

                            return Repeater::make('items')
                                ->relationship('items', function ($query) {
                                    // Only load main-level lines; child lines are nested within their main items
                                    return $query->whereDoesntHave('requestItem', function ($q) {
                                        $q->whereNotNull('parent_id');
                                    });
                                })
                                ->mutateRelationshipDataBeforeFillUsing(function (array $data, SupplierQuote|SupplierQuoteItem $record) use ($request): array {
                                    // When loading for edit: inject child_items (with unit_price) so detail items show saved values
                                    // Filament may pass parent SupplierQuote when filling; only run when we have a main item (SupplierQuoteItem)
                                    if (! $record instanceof SupplierQuoteItem) {
                                        return $data;
                                    }
                                    if (! isset($data['request_item_id'])) {
                                        return $data;
                                    }
                                    $requestItem = $request->items()->find(SafeCast::toInt($data['request_item_id']));
                                    if ($requestItem === null || ! $requestItem->isMainItem()) {
                                        return $data;
                                    }
                                    $childRequestItems = $requestItem->children()->orderBy('sort_order')->get();
                                    if ($childRequestItems->isEmpty()) {
                                        $data['child_items'] = [];

                                        return $data;
                                    }
                                    $supplierQuote = $record->supplierQuote;
                                    if (! $supplierQuote instanceof SupplierQuote) {
                                        return $data;
                                    }
                                    $taxCode = $requestItem->article?->defaultTaxCode;
                                    $childQuoteItems = [];
                                    foreach ($childRequestItems as $childRequestItem) {
                                        $existingQuoteItem = $supplierQuote->items()
                                            ->where('request_item_id', $childRequestItem->id)
                                            ->first();
                                        if ($existingQuoteItem !== null) {
                                            $childQuoteItems[] = [
                                                'id' => $existingQuoteItem->id,
                                                'request_item_id' => $existingQuoteItem->request_item_id,
                                                'description' => $existingQuoteItem->description,
                                                'quantity' => (string) $existingQuoteItem->quantity,
                                                'unit_of_measure_id' => $existingQuoteItem->getAttribute('unit_of_measure_id'),
                                                'unit_price' => (string) (int) (float) $existingQuoteItem->unit_price,
                                                'tax_code_id' => $existingQuoteItem->tax_code_id,
                                                'tax_rate' => (string) $existingQuoteItem->tax_rate,
                                                'is_tax_inclusive' => $existingQuoteItem->is_tax_inclusive,
                                                'line_subtotal' => (string) $existingQuoteItem->line_subtotal,
                                                'line_tax' => (string) $existingQuoteItem->line_tax,
                                                'line_total' => (string) $existingQuoteItem->line_total,
                                            ];
                                        } else {
                                            $childQuoteItems[] = [
                                                'request_item_id' => $childRequestItem->id,
                                                'description' => $childRequestItem->description,
                                                'quantity' => (string) $childRequestItem->quantity,
                                                'unit_of_measure_id' => $childRequestItem->unit_of_measure_id,
                                                'unit_price' => '0',
                                                'tax_code_id' => $taxCode?->id,
                                                'tax_rate' => (string) ($taxCode->rate ?? 0),
                                                'is_tax_inclusive' => $taxCode->is_inclusive_default ?? false,
                                                'line_subtotal' => '0',
                                                'line_tax' => '0',
                                                'line_total' => '0',
                                            ];
                                        }
                                    }
                                    $data['child_items'] = $childQuoteItems;

                                    return $data;
                                })
                                ->mutateRelationshipDataBeforeCreateUsing(function (array $data) use ($relationManager): array {
                                    // Intercept the create process to remove child_items before Filament tries to save it
                                    // Also capture child_items for later processing
                                    if (isset($data['child_items']) && is_array($data['child_items'])) {
                                        $itemId = $data['id'] ?? null;
                                        if ($itemId !== null) {
                                            if (! isset($relationManager->storedChildItemsData)) {
                                                $relationManager->storedChildItemsData = [];
                                            }
                                            $relationManager->storedChildItemsData[$itemId] = $data['child_items'];
                                            \Log::info('Captured child_items in mutateRelationshipDataBeforeCreateUsing', [
                                                'item_id' => $itemId,
                                                'child_items_count' => count($data['child_items']),
                                            ]);
                                        }
                                        // Remove child_items from data so Filament doesn't try to save it
                                        unset($data['child_items']);
                                    }

                                    return $data;
                                })
                                ->mutateRelationshipDataBeforeSaveUsing(function (array $data, SupplierQuoteItem $record) use ($relationManager): array {
                                    // Capture child_items for after() to persist ($data['items'] is often empty in after(), so we store here)
                                    $childItems = $data['child_items'] ?? null;
                                    if (is_array($childItems) && ! empty($childItems)) {
                                        $itemId = $record->id ?? ($data['id'] ?? null);
                                        if ($itemId !== null) {
                                            if (! isset($relationManager->storedChildItemsData)) {
                                                $relationManager->storedChildItemsData = [];
                                            }
                                            $relationManager->storedChildItemsData[$itemId] = $childItems;
                                        }
                                        unset($data['child_items']);
                                    }

                                    return $data;
                                });
                        })()
                            ->afterStateHydrated(function (Set $set, Get $get, $state, $record) use ($request): void {
                                // Populate child_items for each main item after items are loaded from relationship
                                if (! is_array($state) || empty($state)) {
                                    return;
                                }

                                // Get the SupplierQuote record
                                // In a relationship repeater, $record is the individual item, so we need to get the quote differently
                                $supplierQuote = null;
                                try {
                                    // Try to get from the form's owner record
                                    $relationManager = $this;
                                    $supplierQuote = $relationManager->getMountedTableActionRecord();

                                    if (! ($supplierQuote instanceof SupplierQuote)) {
                                        return;
                                    }
                                } catch (\Exception $e) {
                                    return;
                                }

                                // Process each item and populate child_items
                                foreach ($state as $index => $item) {
                                    if (! isset($item['request_item_id']) || ! isset($item['id'])) {
                                        continue;
                                    }

                                    $requestItem = $request->items()->find(SafeCast::toInt($item['request_item_id']));
                                    if ($requestItem === null || ! $requestItem->isMainItem()) {
                                        continue;
                                    }

                                    $childRequestItems = $requestItem->children()->orderBy('sort_order')->get();
                                    if ($childRequestItems->isEmpty()) {
                                        continue;
                                    }

                                    // Get child quote items for this main item
                                    $childQuoteItems = [];
                                    foreach ($childRequestItems as $childRequestItem) {
                                        $existingQuoteItem = $supplierQuote->items()
                                            ->where('request_item_id', $childRequestItem->id)
                                            ->first();

                                        if ($existingQuoteItem !== null) {
                                            $childQuoteItems[] = [
                                                'id' => $existingQuoteItem->id,
                                                'request_item_id' => $existingQuoteItem->request_item_id,
                                                'description' => $existingQuoteItem->description,
                                                'quantity' => (string) $existingQuoteItem->quantity,
                                                'unit_of_measure_id' => $existingQuoteItem->getAttribute('unit_of_measure_id'),
                                                'unit_price' => (string) (int) (float) $existingQuoteItem->unit_price,
                                                'tax_code_id' => $existingQuoteItem->tax_code_id,
                                                'tax_rate' => (string) $existingQuoteItem->tax_rate,
                                                'is_tax_inclusive' => $existingQuoteItem->is_tax_inclusive,
                                                'line_subtotal' => (string) $existingQuoteItem->line_subtotal,
                                                'line_tax' => (string) $existingQuoteItem->line_tax,
                                                'line_total' => (string) $existingQuoteItem->line_total,
                                            ];
                                        } else {
                                            $taxCode = $requestItem->article?->defaultTaxCode;
                                            $childQuoteItems[] = [
                                                'request_item_id' => $childRequestItem->id,
                                                'description' => $childRequestItem->description,
                                                'quantity' => (string) $childRequestItem->quantity,
                                                'unit_of_measure_id' => $childRequestItem->unit_of_measure_id,
                                                'unit_price' => '0',
                                                'tax_code_id' => $taxCode?->id,
                                                'tax_rate' => (string) ($taxCode->rate ?? 0),
                                                'is_tax_inclusive' => $taxCode->is_inclusive_default ?? false,
                                                'line_subtotal' => '0',
                                                'line_tax' => '0',
                                                'line_total' => '0',
                                            ];
                                        }
                                    }

                                    if (! empty($childQuoteItems)) {
                                        $set("items.{$index}.child_items", $childQuoteItems);
                                    }
                                }
                            })
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
                                            ->afterStateUpdated(function (Set $set, Get $get, ?int $state) use ($request): void {
                                                if ($state === null) {
                                                    return;
                                                }
                                                $requestItem = $request->items()->with('article.defaultTaxCode', 'unitOfMeasure', 'children')->find($state);
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

                                                    // NOTE: services main items with children previously attempted to
                                                    // auto-insert their child rows into the in-progress (unsaved) repeater
                                                    // state here via a `$this->getLivewire()` call that does not exist on
                                                    // RelationManager, so this branch always threw and never ran. Detail
                                                    // items are still populated for existing rows via the "Detail Items"
                                                    // nested repeater's own hydration below.
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
                                                        $requestItem = $request->items()->with('unitOfMeasure')->find(SafeCast::toInt($requestItemId));
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
                                            ->visible(function (Get $get): bool {
                                                // For nested child_items, we need to go up more levels to get supplier_id
                                                $supplierId = $get('../../supplier_id') ?? $get('../../../supplier_id');
                                                if ($supplierId === null) {
                                                    return false;
                                                }
                                                $supplier = Company::find(SafeCast::toInt($supplierId));

                                                return $supplier !== null && $supplier->is_taxable;
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
                                            ->columnSpan(2),
                                    ]),
                                Textarea::make('notes')
                                    ->rows(1)
                                    ->columnSpanFull(),
                                // Child items nested within main item for Service requests
                                Section::make('Detail Items')
                                    ->schema([
                                        Repeater::make('child_items')
                                            ->label('Detail Items')
                                            ->schema([
                                                Hidden::make('request_item_id'),
                                                Grid::make(12)
                                                    ->schema([
                                                        TextInput::make('description')
                                                            ->required()
                                                            ->columnSpan(4),
                                                        TextInput::make('quantity')
                                                            ->numeric()
                                                            ->required()
                                                            ->default(1)
                                                            ->step(1) // No decimals for child items
                                                            ->extraInputAttributes(['inputmode' => 'numeric'])
                                                            ->formatStateUsing(fn ($state) => $state !== null ? (string) (int) (float) $state : '1')
                                                            ->dehydrateStateUsing(fn ($state) => $state !== null ? (int) (float) $state : 1)
                                                            ->columnSpan(2)
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                                        Select::make('unit_of_measure_id')
                                                            ->label('Unit')
                                                            ->options(fn (): array => UnitOfMeasure::query()
                                                                ->where('team_id', $request->team_id)
                                                                ->where('is_active', true)
                                                                ->orderBy('label')
                                                                ->get()
                                                                ->mapWithKeys(fn (UnitOfMeasure $unit): array => [
                                                                    $unit->getKey() => $unit->label,
                                                                ])
                                                                ->all())
                                                            ->searchable()
                                                            ->preload()
                                                            ->columnSpan(2),
                                                        TextInput::make('unit_price')
                                                            ->label('Unit Price')
                                                            ->numeric()
                                                            ->required()
                                                            ->default(0)
                                                            ->step(1) // No decimals for child items
                                                            ->extraInputAttributes(['inputmode' => 'numeric'])
                                                            ->formatStateUsing(fn ($state) => $state !== null ? (string) (int) (float) $state : '0')
                                                            ->dehydrateStateUsing(fn ($state) => $state !== null ? (int) (float) $state : 0)
                                                            ->columnSpan(4)
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                                    ]),
                                                Grid::make(12)
                                                    ->schema([
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
                                                            ->columnSpan(4)
                                                            ->live()
                                                            ->visible(fn (Get $get): bool => $this->isSupplierTaxable($get))
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
                                                            ->visible(function (Get $get): bool {
                                                                // For nested child_items, we need to go up more levels to get supplier_id
                                                                $supplierId = $get('../../supplier_id') ?? $get('../../../supplier_id');
                                                                if ($supplierId === null) {
                                                                    return false;
                                                                }
                                                                $supplier = Company::find(SafeCast::toInt($supplierId));

                                                                return $supplier !== null && $supplier->is_taxable;
                                                            }),
                                                        TextInput::make('line_total')
                                                            ->label('Line Total')
                                                            ->numeric()
                                                            ->step(1) // No decimals for child items
                                                            ->extraInputAttributes(['inputmode' => 'numeric'])
                                                            ->formatStateUsing(fn ($state) => $state !== null ? (string) (int) (float) $state : '0')
                                                            ->dehydrateStateUsing(fn ($state) => $state !== null ? (int) (float) $state : 0)
                                                            ->disabled()
                                                            ->dehydrated()
                                                            ->columnSpan(6),
                                                    ]),
                                            ])
                                            ->columns(1)
                                            ->defaultItems(0)
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => $state['description'] ?? null)
                                            ->dehydrated(true) // Include in form state so we can access it in callbacks
                                            ->afterStateHydrated(function (Set $set, Get $get, $state, $record) use ($request): void {
                                                // Load child_items from parent item's data when repeater hydrates
                                                // Get request_item_id from parent item context
                                                $requestItemId = $get('../../request_item_id');
                                                if ($requestItemId === null) {
                                                    return;
                                                }

                                                // Try to get from parent item's child_items first (set by fillForm)
                                                $parentItemData = $get('../../');
                                                if (isset($parentItemData['child_items']) && is_array($parentItemData['child_items']) && ! empty($parentItemData['child_items'])) {
                                                    $set('child_items', $parentItemData['child_items']);

                                                    return;
                                                }

                                                // If state is already populated, use it
                                                if (is_array($state) && ! empty($state)) {
                                                    return; // Already has data
                                                }

                                                // Fallback: load from request items and existing quote items
                                                $requestItem = $request->items()->find(SafeCast::toInt($requestItemId));
                                                if ($requestItem === null || ! $requestItem->isMainItem()) {
                                                    return;
                                                }

                                                $childRequestItems = $requestItem->children()->orderBy('sort_order')->get();
                                                if ($childRequestItems->isEmpty()) {
                                                    return;
                                                }

                                                // Try to get the SupplierQuote record to load existing child quote items
                                                $supplierQuote = null;
                                                try {
                                                    // Try to get from form context
                                                    $relationManager = $this;
                                                    $supplierQuote = $relationManager->getMountedTableActionRecord();
                                                } catch (\Exception $e) {
                                                    // Can't get supplier quote, continue with defaults
                                                }

                                                $taxCode = $requestItem->article?->defaultTaxCode;
                                                $childQuoteItems = [];
                                                foreach ($childRequestItems as $childRequestItem) {
                                                    // Try to find existing quote item for this child request item
                                                    $existingQuoteItem = null;
                                                    if ($supplierQuote instanceof SupplierQuote) {
                                                        $existingQuoteItem = $supplierQuote->items()
                                                            ->where('request_item_id', $childRequestItem->id)
                                                            ->first();
                                                    }

                                                    if ($existingQuoteItem !== null) {
                                                        $childQuoteItems[] = [
                                                            'id' => $existingQuoteItem->id,
                                                            'request_item_id' => $existingQuoteItem->request_item_id,
                                                            'description' => $existingQuoteItem->description,
                                                            'quantity' => (string) $existingQuoteItem->quantity,
                                                            'unit_of_measure_id' => $existingQuoteItem->getAttribute('unit_of_measure_id'),
                                                            'unit_price' => (string) (int) (float) $existingQuoteItem->unit_price,
                                                            'tax_code_id' => $existingQuoteItem->tax_code_id,
                                                            'tax_rate' => (string) $existingQuoteItem->tax_rate,
                                                            'is_tax_inclusive' => $existingQuoteItem->is_tax_inclusive,
                                                            'line_subtotal' => (string) $existingQuoteItem->line_subtotal,
                                                            'line_tax' => (string) $existingQuoteItem->line_tax,
                                                            'line_total' => (string) $existingQuoteItem->line_total,
                                                        ];
                                                    } else {
                                                        // Create new child item data
                                                        $childQuoteItems[] = [
                                                            'request_item_id' => $childRequestItem->id,
                                                            'description' => $childRequestItem->description,
                                                            'quantity' => (string) $childRequestItem->quantity,
                                                            'unit_of_measure_id' => $childRequestItem->unit_of_measure_id,
                                                            'unit_price' => '0',
                                                            'tax_code_id' => $taxCode?->id,
                                                            'tax_rate' => (string) ($taxCode->rate ?? 0),
                                                            'is_tax_inclusive' => $taxCode->is_inclusive_default ?? false,
                                                            'line_subtotal' => '0',
                                                            'line_tax' => '0',
                                                            'line_total' => '0',
                                                        ];
                                                    }
                                                }

                                                if (! empty($childQuoteItems)) {
                                                    $set('child_items', $childQuoteItems);
                                                }
                                            }),
                                    ])
                                    ->collapsible()
                                    ->collapsed()
                                    ->visible(function (Get $get, $record) use ($request): bool {
                                        $requestItemId = $get('request_item_id');
                                        if ($requestItemId === null && $record !== null) {
                                            $requestItemId = $record->request_item_id;
                                        }
                                        if ($requestItemId === null) {
                                            return false;
                                        }
                                        $requestItem = $request->items()->find(SafeCast::toInt($requestItemId));

                                        return $requestItem !== null
                                            && $requestItem->isMainItem()
                                            && $requestItem->supportsItemHierarchy();
                                    }),
                            ])
                            ->columns(1)
                            ->defaultItems(0)
                            ->addable(false)
                            ->reorderable()
                            ->orderColumn('sort_order')
                            ->collapsible()
                            ->itemLabel(function (array $state, Get $get) use ($request): ?string {
                                $description = $state['description'] ?? null;
                                if ($description === null) {
                                    return null;
                                }

                                // Check if this is a child item and indent it visually
                                $requestItemId = $state['request_item_id'] ?? null;
                                if ($requestItemId !== null) {
                                    $requestItem = $request->items()->find(SafeCast::toInt($requestItemId));
                                    if ($requestItem !== null && $requestItem->isChildItem()) {
                                        // Indent child items to show they're nested within main item
                                        return '  └─ '.$description;
                                    }
                                }

                                return $description;
                            }),
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
                    // A declined RFQ keeps PENDING status; the timestamp drives the badge.
                    ->formatStateUsing(fn (SupplierQuote $record): string => $record->declined_at !== null
                        ? 'Declined'
                        : $record->status->getLabel())
                    ->color(fn (SupplierQuote $record): string => $record->declined_at !== null
                        ? 'danger'
                        : $record->status->getColor())
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
                    ->modalCancelAction(false)
                    ->visible(function (): bool {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();

                        // Only show Create QE if no QE exists
                        if ($request->quotationEvaluations()->exists()) {
                            return false;
                        }

                        // When user has obtained+selected quote, they can skip QE and go to Buyer Quotes
                        if ($request->hasObtainedSelectedSupplierQuote()) {
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
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(function (?SupplierQuote $record): string {
                            if ($record === null) {
                                return 'Edit';
                            }
                            $record->load('media');

                            return $record->getMedia('quotation')->isNotEmpty() ? 'Input price' : 'Edit';
                        })
                        ->icon('heroicon-o-pencil-square')
                        ->size(Size::Small)
                        ->visible(function (?SupplierQuote $record): bool {
                            if ($record === null) {
                                return false;
                            }
                            $record->load('media');

                            // Show when quotation document is uploaded so user can input supplier prices (per post-upload notification)
                            return $record->getMedia('quotation')->isNotEmpty();
                        })
                        ->mutateFormDataUsing(function (array $data, SupplierQuote $record): array {
                            $request = $record->request;
                            if ($request === null) {
                                return $data;
                            }

                            // Save child items from form data (child_items may not be passed into mutateRelationshipDataBeforeSaveUsing)
                            $itemsData = $data['items'] ?? [];
                            $mainItems = $record->items()
                                ->whereHas('requestItem', function ($q) {
                                    $q->whereNull('parent_id');
                                })
                                ->get();

                            foreach ($mainItems as $mainQuoteItem) {
                                $requestItemId = $mainQuoteItem->request_item_id;
                                $childItemsData = null;
                                $itemDataByKey = $itemsData['record-'.$mainQuoteItem->id] ?? null;
                                if (is_array($itemDataByKey) && ! empty($itemDataByKey['child_items'] ?? [])) {
                                    $childItemsData = $itemDataByKey['child_items'];
                                }
                                if ((empty($childItemsData) || ! is_array($childItemsData)) && is_array($itemsData)) {
                                    foreach ($itemsData as $itemData) {
                                        if (isset($itemData['request_item_id']) && (int) $itemData['request_item_id'] === (int) $requestItemId) {
                                            $childItemsData = $itemData['child_items'] ?? null;
                                            break;
                                        }
                                    }
                                }
                                if (empty($childItemsData) || ! is_array($childItemsData)) {
                                    continue;
                                }
                                if ($requestItemId === null) {
                                    continue;
                                }

                                $requestItem = $request->items()->find($requestItemId);
                                if ($requestItem === null || ! $requestItem->isMainItem()) {
                                    continue;
                                }

                                $childRequestItems = $requestItem->children()->orderBy('sort_order')->get();
                                $childRequestItemIds = $childRequestItems->pluck('id')->toArray();

                                foreach ($childItemsData as $childItemData) {
                                    $childRequestItemId = $childItemData['request_item_id'] ?? null;
                                    if ($childRequestItemId === null || ! in_array($childRequestItemId, $childRequestItemIds, true)) {
                                        continue;
                                    }

                                    $childQuoteItem = $record->items()
                                        ->where('request_item_id', $childRequestItemId)
                                        ->first();

                                    $unitPrice = isset($childItemData['unit_price']) && $childItemData['unit_price'] !== ''
                                        ? (float) $childItemData['unit_price']
                                        : ($childQuoteItem?->unit_price ? (float) $childQuoteItem->unit_price : 0);
                                    $quantity = isset($childItemData['quantity']) && $childItemData['quantity'] !== ''
                                        ? (float) $childItemData['quantity']
                                        : ($childQuoteItem?->quantity ? (float) $childQuoteItem->quantity : 1);
                                    $taxRate = isset($childItemData['tax_rate']) && $childItemData['tax_rate'] !== ''
                                        ? (float) $childItemData['tax_rate']
                                        : ($childQuoteItem?->tax_rate ? (float) $childQuoteItem->tax_rate : 0);
                                    $lineSubtotal = isset($childItemData['line_subtotal']) && $childItemData['line_subtotal'] !== ''
                                        ? (float) $childItemData['line_subtotal']
                                        : ($childQuoteItem?->line_subtotal ? (float) $childQuoteItem->line_subtotal : 0);
                                    $lineTax = isset($childItemData['line_tax']) && $childItemData['line_tax'] !== ''
                                        ? (float) $childItemData['line_tax']
                                        : ($childQuoteItem?->line_tax ? (float) $childQuoteItem->line_tax : 0);
                                    $lineTotal = isset($childItemData['line_total']) && $childItemData['line_total'] !== ''
                                        ? (float) $childItemData['line_total']
                                        : ($childQuoteItem?->line_total ? (float) $childQuoteItem->line_total : 0);

                                    if ($childQuoteItem === null) {
                                        $taxCodeId = $childItemData['tax_code_id'] ?? $requestItem->article?->default_tax_code_id;
                                        $taxCode = $taxCodeId ? TaxCode::find(SafeCast::toInt($taxCodeId)) : ($requestItem->article?->defaultTaxCode);
                                        SupplierQuoteItem::create([
                                            'supplier_quote_id' => $record->id,
                                            'request_item_id' => $childRequestItemId,
                                            'article_id' => null,
                                            'description' => $childItemData['description'] ?? '',
                                            'quantity' => (string) $quantity,
                                            'unit_of_measure_id' => $childItemData['unit_of_measure_id'] ?? null,
                                            'unit_price' => (string) $unitPrice,
                                            'tax_code_id' => $taxCodeId,
                                            'tax_rate' => (string) ($taxRate > 0 ? $taxRate : ($taxCode->rate ?? 0)),
                                            'is_tax_inclusive' => $childItemData['is_tax_inclusive'] ?? $taxCode->is_inclusive_default ?? false,
                                            'line_subtotal' => (string) $lineSubtotal,
                                            'line_tax' => (string) $lineTax,
                                            'line_total' => (string) $lineTotal,
                                            'sort_order' => $mainQuoteItem->sort_order + 1,
                                        ]);
                                    } else {
                                        $childQuoteItem->update([
                                            'description' => $childItemData['description'] ?? $childQuoteItem->description,
                                            'quantity' => (string) $quantity,
                                            'unit_of_measure_id' => $childItemData['unit_of_measure_id'] ?? $childQuoteItem->getAttribute('unit_of_measure_id'),
                                            'unit_price' => (string) $unitPrice,
                                            'tax_code_id' => $childItemData['tax_code_id'] ?? $childQuoteItem->tax_code_id,
                                            'tax_rate' => (string) $taxRate,
                                            'is_tax_inclusive' => $childItemData['is_tax_inclusive'] ?? $childQuoteItem->is_tax_inclusive,
                                            'line_subtotal' => (string) $lineSubtotal,
                                            'line_tax' => (string) $lineTax,
                                            'line_total' => (string) $lineTotal,
                                        ]);
                                    }
                                }
                            }

                            $this->storedChildItemsData = null;

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
                            if (! $hasPrices) {
                                $hasPrices = $record->items()->where('unit_price', '>', 0)->exists();
                            }
                            if ($hasPrices && ($data['status'] ?? null) === SupplierQuoteStatus::PENDING->value) {
                                $data['status'] = ($data['obtained'] ?? false)
                                    ? SupplierQuoteStatus::SELECTED->value
                                    : SupplierQuoteStatus::RECEIVED->value;
                            }

                            return $data;
                        })
                        ->after(function (SupplierQuote $record, array $data): void {
                            $request = $record->request;
                            if ($request === null) {
                                return;
                            }

                            // Child items are captured in storedChildItemsData by mutateRelationshipDataBeforeSaveUsing;
                            // $data['items'] is often empty in after(), so we rely on storedChildItemsData to persist them.
                            $mainItems = $record->items()
                                ->whereHas('requestItem', function ($q): void {
                                    $q->whereNull('parent_id');
                                })
                                ->get();

                            $itemsData = $data['items'] ?? [];
                            foreach ($mainItems as $mainQuoteItem) {
                                $requestItemId = $mainQuoteItem->request_item_id;
                                $childItemsData = null;
                                if (is_array($itemsData) && ! empty($itemsData)) {
                                    $itemDataByKey = $itemsData['record-'.$mainQuoteItem->id] ?? null;
                                    if (is_array($itemDataByKey) && ! empty($itemDataByKey['child_items'] ?? [])) {
                                        $childItemsData = $itemDataByKey['child_items'];
                                    }
                                    if ((empty($childItemsData) || ! is_array($childItemsData))) {
                                        foreach ($itemsData as $itemData) {
                                            if (isset($itemData['request_item_id']) && (int) $itemData['request_item_id'] === (int) $requestItemId) {
                                                $childItemsData = $itemData['child_items'] ?? null;
                                                break;
                                            }
                                        }
                                    }
                                }
                                if ((empty($childItemsData) || ! is_array($childItemsData)) && isset($this->storedChildItemsData[$mainQuoteItem->id])) {
                                    $childItemsData = $this->storedChildItemsData[$mainQuoteItem->id];
                                }
                                if (empty($childItemsData) || ! is_array($childItemsData)) {
                                    continue;
                                }
                                if ($requestItemId === null) {
                                    continue;
                                }

                                $requestItem = $request->items()->find($requestItemId);
                                if ($requestItem === null || ! $requestItem->isMainItem()) {
                                    continue;
                                }

                                $childRequestItems = $requestItem->children()->orderBy('sort_order')->get();
                                $childRequestItemIds = $childRequestItems->pluck('id')->toArray();

                                foreach ($childItemsData as $childItemData) {
                                    $childRequestItemId = $childItemData['request_item_id'] ?? null;
                                    if ($childRequestItemId === null || ! in_array($childRequestItemId, $childRequestItemIds, true)) {
                                        continue;
                                    }

                                    $childQuoteItem = $record->items()
                                        ->where('request_item_id', $childRequestItemId)
                                        ->first();

                                    $unitPrice = isset($childItemData['unit_price']) && $childItemData['unit_price'] !== ''
                                        ? (float) $childItemData['unit_price']
                                        : ($childQuoteItem?->unit_price ? (float) $childQuoteItem->unit_price : 0);
                                    $quantity = isset($childItemData['quantity']) && $childItemData['quantity'] !== ''
                                        ? (float) $childItemData['quantity']
                                        : ($childQuoteItem?->quantity ? (float) $childQuoteItem->quantity : 1);
                                    $taxRate = isset($childItemData['tax_rate']) && $childItemData['tax_rate'] !== ''
                                        ? (float) $childItemData['tax_rate']
                                        : ($childQuoteItem?->tax_rate ? (float) $childQuoteItem->tax_rate : 0);
                                    $lineSubtotal = isset($childItemData['line_subtotal']) && $childItemData['line_subtotal'] !== ''
                                        ? (float) $childItemData['line_subtotal']
                                        : ($childQuoteItem?->line_subtotal ? (float) $childQuoteItem->line_subtotal : 0);
                                    $lineTax = isset($childItemData['line_tax']) && $childItemData['line_tax'] !== ''
                                        ? (float) $childItemData['line_tax']
                                        : ($childQuoteItem?->line_tax ? (float) $childQuoteItem->line_tax : 0);
                                    $lineTotal = isset($childItemData['line_total']) && $childItemData['line_total'] !== ''
                                        ? (float) $childItemData['line_total']
                                        : ($childQuoteItem?->line_total ? (float) $childQuoteItem->line_total : 0);

                                    if ($childQuoteItem === null) {
                                        $taxCodeId = $childItemData['tax_code_id'] ?? $requestItem->article?->default_tax_code_id;
                                        $taxCode = $taxCodeId ? TaxCode::find(SafeCast::toInt($taxCodeId)) : ($requestItem->article?->defaultTaxCode);
                                        SupplierQuoteItem::create([
                                            'supplier_quote_id' => $record->id,
                                            'request_item_id' => $childRequestItemId,
                                            'article_id' => null,
                                            'description' => $childItemData['description'] ?? '',
                                            'quantity' => (string) $quantity,
                                            'unit_of_measure_id' => $childItemData['unit_of_measure_id'] ?? null,
                                            'unit_price' => (string) $unitPrice,
                                            'tax_code_id' => $taxCodeId,
                                            'tax_rate' => (string) ($taxRate > 0 ? $taxRate : ($taxCode->rate ?? 0)),
                                            'is_tax_inclusive' => $childItemData['is_tax_inclusive'] ?? $taxCode->is_inclusive_default ?? false,
                                            'line_subtotal' => (string) $lineSubtotal,
                                            'line_tax' => (string) $lineTax,
                                            'line_total' => (string) $lineTotal,
                                            'sort_order' => $mainQuoteItem->sort_order + 1,
                                        ]);
                                    } else {
                                        $childQuoteItem->update([
                                            'description' => $childItemData['description'] ?? $childQuoteItem->description,
                                            'quantity' => (string) $quantity,
                                            'unit_of_measure_id' => $childItemData['unit_of_measure_id'] ?? $childQuoteItem->getAttribute('unit_of_measure_id'),
                                            'unit_price' => (string) $unitPrice,
                                            'tax_code_id' => $childItemData['tax_code_id'] ?? $childQuoteItem->tax_code_id,
                                            'tax_rate' => (string) $taxRate,
                                            'is_tax_inclusive' => $childItemData['is_tax_inclusive'] ?? $childQuoteItem->is_tax_inclusive,
                                            'line_subtotal' => (string) $lineSubtotal,
                                            'line_tax' => (string) $lineTax,
                                            'line_total' => (string) $lineTotal,
                                        ]);
                                    }
                                }
                            }

                            $this->storedChildItemsData = null;

                            // Remove orphan line items (no request_item_id) on service requests
                            $record->items()->whereNull('request_item_id')->delete();
                            $record->recalculateTotals();

                            // Update quote status when prices are present: SELECTED if obtained, otherwise RECEIVED
                            $record->refresh();
                            $hasPrices = $record->items()->where('unit_price', '>', 0)->exists();
                            $total = (float) $record->total;
                            if ($record->status === SupplierQuoteStatus::PENDING && ($hasPrices || $total > 0)) {
                                $record->status = $record->obtained
                                    ? SupplierQuoteStatus::SELECTED
                                    : SupplierQuoteStatus::RECEIVED;
                                $record->save();
                            }
                        })
                        ->fillForm(function (SupplierQuote $record): array {
                            $data = $record->toArray();

                            $request = $record->request;
                            if ($request === null) {
                                return $data;
                            }

                            if (isset($data['items'])) {
                                $items = $data['items'];
                                $newItems = [];

                                foreach ($items as $item) {
                                    if (isset($item['request_item_id'])) {
                                        $requestItem = $request->items()->with('children')->find(SafeCast::toInt($item['request_item_id']));
                                        if ($requestItem !== null && $requestItem->isChildItem()) {
                                            continue;
                                        }
                                        if ($requestItem !== null && $requestItem->isMainItem() && $requestItem->children()->count() > 0) {
                                            $childRequestItems = $requestItem->children()->orderBy('sort_order')->get();
                                            $childQuoteItems = [];

                                            foreach ($childRequestItems as $childRequestItem) {
                                                $existingQuoteItem = $record->items()
                                                    ->where('request_item_id', $childRequestItem->id)
                                                    ->first();

                                                if ($existingQuoteItem !== null) {
                                                    $childQuoteItems[] = [
                                                        'id' => $existingQuoteItem->id,
                                                        'request_item_id' => $existingQuoteItem->request_item_id,
                                                        'description' => $existingQuoteItem->description,
                                                        'quantity' => (string) $existingQuoteItem->quantity,
                                                        'unit_of_measure_id' => $existingQuoteItem->getAttribute('unit_of_measure_id'),
                                                        'unit_price' => (string) $existingQuoteItem->unit_price,
                                                        'tax_code_id' => $existingQuoteItem->tax_code_id,
                                                        'tax_rate' => (string) $existingQuoteItem->tax_rate,
                                                        'is_tax_inclusive' => $existingQuoteItem->is_tax_inclusive,
                                                        'line_subtotal' => (string) $existingQuoteItem->line_subtotal,
                                                        'line_tax' => (string) $existingQuoteItem->line_tax,
                                                        'line_total' => (string) $existingQuoteItem->line_total,
                                                    ];
                                                } else {
                                                    $taxCode = $requestItem->article?->defaultTaxCode;
                                                    $childQuoteItems[] = [
                                                        'request_item_id' => $childRequestItem->id,
                                                        'description' => $childRequestItem->description,
                                                        'quantity' => (string) $childRequestItem->quantity,
                                                        'unit_of_measure_id' => $childRequestItem->unit_of_measure_id,
                                                        'unit_price' => '0',
                                                        'tax_code_id' => $taxCode?->id,
                                                        'tax_rate' => (string) ($taxCode->rate ?? 0),
                                                        'is_tax_inclusive' => $taxCode->is_inclusive_default ?? false,
                                                        'line_subtotal' => '0',
                                                        'line_tax' => '0',
                                                        'line_total' => '0',
                                                    ];
                                                }
                                            }

                                            $item['child_items'] = $childQuoteItems;
                                        }
                                    }

                                    $newItems[] = $item;
                                }

                                $data['items'] = $newItems;
                            }

                            return $data;
                        }),
                    Action::make('quotation')
                        ->label(function (SupplierQuote $record): string {
                            $record->load('media');
                            // When request has additional items, always show Upload so user must re-upload before inputting prices
                            if ($record->hasAdditionalRequestItems()) {
                                return 'Upload quotation';
                            }

                            return $record->getMedia('quotation')->isNotEmpty() ? 'View quotation' : 'Upload quotation';
                        })
                        ->icon(function (SupplierQuote $record): string {
                            $record->load('media');
                            if ($record->hasAdditionalRequestItems()) {
                                return 'heroicon-o-document-arrow-up';
                            }

                            return $record->getMedia('quotation')->isNotEmpty() ? 'heroicon-o-eye' : 'heroicon-o-document-arrow-up';
                        })
                        ->color('gray')
                        ->size(Size::Small)
                        ->slideOver()
                        ->form(function (SupplierQuote $record): array {
                            $record->load('media');
                            // When request has additional items, always show upload form so user must re-upload
                            if ($record->hasAdditionalRequestItems()) {
                                return $this->getQuotationUploadFormSchema($record);
                            }

                            return $record->getMedia('quotation')->isNotEmpty()
                                ? $this->getQuotationViewFormSchema($record)
                                : $this->getQuotationUploadFormSchema($record);
                        })
                        ->modalSubmitAction(function (Action $action): Action|false {
                            $record = $this->getMountedAction()?->getRecord();
                            if (! $record instanceof SupplierQuote) {
                                return false;
                            }
                            // Show Upload button when no document yet, or when request has additional items (must re-upload)
                            if ($record->getMedia('quotation')->isEmpty() || $record->hasAdditionalRequestItems()) {
                                return $action;
                            }

                            return false;
                        })
                        ->modalSubmitActionLabel('Save')
                        ->modalCancelActionLabel('Close')
                        ->action(function (SupplierQuote $record, array $data): void {
                            $record->load('media');
                            $isReupload = $record->hasAdditionalRequestItems() && $record->getMedia('quotation')->isNotEmpty();
                            if (! $isReupload && $record->getMedia('quotation')->isNotEmpty()) {
                                return;
                            }
                            if ($isReupload) {
                                $record->clearMediaCollection('quotation');
                            }
                            $files = $data['quotation_file'] ?? null;
                            $paths = is_array($files) ? $files : ($files !== null ? [$files] : []);
                            $added = 0;
                            $rejected = 0;
                            foreach ($paths as $file) {
                                if (! is_string($file)) {
                                    continue;
                                }
                                try {
                                    $added += count(app(AttachUploadedFiles::class)->execute($record, [$file], 'quotation', SupplierQuote::QUOTATION_UPLOAD_DIRECTORY));
                                } catch (FileUnacceptableForCollection) {
                                    $rejected++;
                                }
                            }
                            if ($added > 0) {
                                Notification::make()
                                    ->title('Quotation uploaded')
                                    ->body('Quotation document has been uploaded. You can now input supplier prices.')
                                    ->success()
                                    ->send();
                            }
                            if ($rejected > 0) {
                                Notification::make()
                                    ->title('File rejected')
                                    ->body('The file content is not an accepted document type (PDF, Excel, Word, PNG, JPEG). Nothing was attached.')
                                    ->danger()
                                    ->send();
                            } elseif ($added === 0) {
                                Notification::make()
                                    ->title('Upload failed')
                                    ->body('No document was attached. Re-select the file and click Save.')
                                    ->danger()
                                    ->send();
                            }
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
     * Upload form schema for supplier quote quotation.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function getQuotationUploadFormSchema(SupplierQuote $record): array
    {
        return [
            Section::make('Upload Quotation')
                ->schema([
                    FileUpload::make('quotation_file')
                        ->label('Quotation document')
                        ->helperText('Upload the supplier quotation document (PDF, Excel, Word, Images), then click Save.')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'image/png',
                            'image/jpeg',
                            'image/jpg',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ])
                        ->disk('local')
                        ->directory(SupplierQuote::QUOTATION_UPLOAD_DIRECTORY)
                        ->visibility('private')
                        ->downloadable()
                        ->openable()
                        ->maxSize(10240)
                        ->required(),
                ]),
        ];
    }

    /**
     * View form schema for supplier quote quotation (list uploaded file).
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function getQuotationViewFormSchema(SupplierQuote $record): array
    {
        return [
            Section::make('Quotation document')
                ->schema([
                    ViewField::make('quotation_list')
                        ->label('')
                        ->view('filament.forms.components.supplier-quote-quotation-list')
                        ->dehydrated(false),
                ]),
        ];
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

    /**
     * Check if the supplier selected in the form is taxable.
     */
    private function isSupplierTaxable(Get $get): bool
    {
        // Get supplier_id from the parent form (go up from repeater item to form level)
        $supplierId = $get('../../supplier_id');

        if ($supplierId === null) {
            // When editing existing quote, check the record's supplier
            $record = $this->getMountedTableActionRecord();
            if ($record instanceof SupplierQuote && $record->supplier_id !== null) {
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
    private function isRecordSupplierTaxable(?SupplierQuote $record): bool
    {
        if (! $record instanceof \App\Models\SupplierQuote || $record->supplier_id === null) {
            return true; // Default to showing tax fields
        }

        $supplier = Company::query()->find($record->supplier_id);

        return $supplier->is_taxable ?? true;
    }
}
