<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\BuyerQuoteCreationMode;
use App\Enums\BuyerQuoteStatus;
use App\Enums\CentralPurchasingRole;
use App\Enums\Erp\PriceBasis;
use App\Enums\PrepaymentType;
use App\Enums\RequestStage;
use App\Enums\SupplierQuoteStatus;
use App\Filament\Actions\DownloadPdfAction;
use App\Filament\Forms\Components\KeyAccountSelect;
use App\Filament\Resources\ProfitAndLossResource;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\Currency;
use App\Models\ProfitAndLoss;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Erp\Financial\LineCalculator;
use App\Services\Erp\Financial\MarginConvention;
use App\Services\TeamMemberService;
use App\Support\Media\DocumentPathGenerator;
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
use Illuminate\Validation\ValidationException;

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
            ViewField::make('expired_alert')
                ->label('')
                ->view('filament.forms.components.buyer-quote-expired-alert')
                ->visible(fn (?BuyerQuote $record): bool => $record !== null && $record->exists && $record->is_expired)
                ->dehydrated(false),
            Section::make('Quote Creation')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('creation_mode')
                                ->label('Creation mode')
                                ->options(BuyerQuoteCreationMode::class)
                                ->placeholder('Select creation mode')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, mixed $state) use ($request): void {
                                    $set('supplier_quote_ids', []);
                                    $set('supplier_quote_id', null);
                                    $this->resetSupplierQuoteFormData($set);

                                    $mode = $this->normalizeCreationMode($state);
                                    if ($mode === BuyerQuoteCreationMode::CONSOLIDATED) {
                                        $ids = $this->getSelectedSupplierQuoteIds($request);
                                        $set('supplier_quote_ids', $ids);
                                        $this->applyBuiltSupplierQuoteData($set, $request, $ids);
                                    }
                                }),
                            Select::make('supplier_quote_ids')
                                ->label('Supplier quotes')
                                ->multiple()
                                ->required(fn (Get $get): bool => $this->isConsolidatedMode($get))
                                ->options(fn (): array => $this->getSupplierQuoteOptions($request))
                                ->visible(fn (Get $get): bool => $this->isConsolidatedMode($get))
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?array $state) use ($request): void {
                                    $this->applyBuiltSupplierQuoteData($set, $request, is_array($state) ? array_filter(array_map('intval', $state)) : []);
                                }),
                            Select::make('supplier_quote_id')
                                ->label('Supplier quote')
                                ->required(fn (Get $get): bool => $this->isPerSupplierMode($get))
                                ->options(fn (): array => $this->getSupplierQuoteOptions($request))
                                ->visible(fn (Get $get): bool => $this->isPerSupplierMode($get))
                                ->live()
                                ->afterStateUpdated(function (Set $set, mixed $state) use ($request): void {
                                    $supplierQuoteId = is_numeric($state) ? (int) $state : null;
                                    $this->applyBuiltSupplierQuoteData($set, $request, $supplierQuoteId !== null ? [$supplierQuoteId] : []);
                                }),
                        ]),
                ])
                ->visible(fn (?Model $record): bool => $this->isCreatingBuyerQuote($record))
                ->collapsible(false),
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
                ])
                ->visible(fn (?Model $record, Get $get): bool => $this->showsSupplierQuoteDetails($record, $get)),

            Section::make('Payment Terms')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('prepayment_type')
                                ->label('Prepayment Type')
                                ->options(PrepaymentType::class)
                                ->default(PrepaymentType::PERCENT)
                                ->selectablePlaceholder(false)
                                ->live(),
                            TextInput::make('prepayment_amount')
                                ->label('Prepayment')
                                ->numeric()
                                ->default(function (Get $get) use ($request): int|float {
                                    $ids = $this->resolveSupplierQuoteIdsFromGet($get);
                                    if (count($ids) !== 1) {
                                        return 0;
                                    }
                                    $sq = SupplierQuote::query()
                                        ->where('request_id', $request->getKey())
                                        ->whereKey($ids[0])
                                        ->first();
                                    if ($sq === null) {
                                        return 0;
                                    }

                                    // Prefer prepayment_percent when type is PERCENT; fall back to prepayment_amount when percent is 0
                                    if ($sq->prepayment_type === PrepaymentType::PERCENT) {
                                        return (int) $sq->prepayment_percent > 0
                                            ? (int) $sq->prepayment_percent
                                            : (int) round((float) $sq->prepayment_amount);
                                    }

                                    return (float) $sq->prepayment_amount;
                                })
                                ->step(1)
                                ->minValue(0)
                                ->live()
                                ->maxValue(fn (Get $get): ?int => $get('prepayment_type') === PrepaymentType::PERCENT->value ? 100 : null)
                                ->suffix(fn (Get $get): string => $get('prepayment_type') === PrepaymentType::PERCENT->value ? '%' : '')
                                ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (string) (int) round((float) $state) : null),
                        ]),
                    Repeater::make('paymentTerms')
                        ->relationship()
                        ->live()
                        ->visible(function (): bool {
                            /** @var Request $request */
                            $request = $this->getOwnerRecord();
                            $buyer = $request->buyer;

                            return $buyer !== null ? ($buyer->credit_status ?? true) : true;
                        })
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
                                        ->visible(fn (): bool => $this->getOwnerRequest()->hasJobProgress())
                                        ->live(),
                                ]),
                        ])
                        ->defaultItems(1)
                        ->itemLabel(function (array $state): ?string {
                            if (! isset($state['due_days'], $state['percentage'])) {
                                return null;
                            }
                            $label = "{$state['due_days']} days - {$state['percentage']}%";
                            if ($this->getOwnerRequest()->hasJobProgress() && isset($state['job_progress']) && $state['job_progress'] !== '') {
                                $label .= " - {$state['job_progress']}%";
                            }

                            return $label;
                        })
                        ->addActionLabel('Add Payment Terms')
                        ->reorderableWithButtons()
                        ->collapsible(),
                ])
                ->visible(fn (?Model $record, Get $get): bool => $this->showsSupplierQuoteDetails($record, $get))
                ->collapsible(),

            Section::make('Line Items')
                ->schema([
                    Repeater::make('items')
                        ->relationship(
                            'items',
                            function ($query) {
                                // Only load main-level lines; child lines are nested within their main items
                                return $query->whereDoesntHave('requestItem', function ($q) {
                                    $q->whereNotNull('parent_id');
                                });
                            }
                        )
                        ->mutateRelationshipDataBeforeFillUsing(function (array $data, $record) use ($request): array {
                            $data['quantity'] = (string) (int) round((float) ($data['quantity'] ?? 0));
                            // Prefill margin_percent_input so the Margin % field is never empty; round to int so all items show same default (e.g. 3)
                            $stored = $data['margin_percent'] ?? ($record->margin_percent ?? null);
                            $default = (int) ceil($this->currentTeam()?->getErpSettings()->default_margin_percent ?? 3.0);
                            $data['margin_percent_input'] = ($stored !== null && $stored !== '' && (float) $stored > 0)
                                ? (int) round((float) $stored)
                                : $default;

                            // Add child_items to each MAIN item when loading from relationship
                            // $record here is the BuyerQuoteItem, we need to check if it's a main item
                            if (isset($data['request_item_id'])) {
                                // Check if this is a main item (not a child item)
                                $requestItem = $request->items()->find(\App\Support\SafeCast::toInt($data['request_item_id']));
                                if ($requestItem !== null && $requestItem->parent_id === null && $requestItem->children()->exists()) {
                                    // Get the BuyerQuote from the BuyerQuoteItem's relationship
                                    $buyerQuote = null;
                                    if ($record instanceof \App\Models\BuyerQuoteItem) {
                                        // Load the relationship if not already loaded
                                        if (! $record->relationLoaded('buyerQuote')) {
                                            $record->load('buyerQuote');
                                        }
                                        $buyerQuote = $record->buyerQuote;
                                    } elseif (isset($data['buyer_quote_id'])) {
                                        $buyerQuote = \App\Models\BuyerQuote::find(\App\Support\SafeCast::toInt($data['buyer_quote_id']));
                                    }

                                    if ($buyerQuote !== null) {
                                        // Get child BuyerQuoteItems for this main item
                                        $childRequestItemIds = $requestItem->children()->pluck('id')->toArray();
                                        $childBuyerQuoteItems = $buyerQuote->items()
                                            ->whereIn('request_item_id', $childRequestItemIds)
                                            ->orderBy('sort_order')
                                            ->get();

                                        if ($childBuyerQuoteItems->isNotEmpty()) {
                                            // Get default tax code as fallback
                                            $defaultTaxCode = TaxCode::query()
                                                ->where('team_id', $request->team_id)
                                                ->where('is_default', true)
                                                ->where('is_active', true)
                                                ->first();

                                            // Get main item's tax information (this is the current item being processed)
                                            // Use the current record's tax info as the main item's tax info
                                            $mainItemTaxCodeId = $data['tax_code_id'] ?? $record->tax_code_id ?? $defaultTaxCode?->getKey();
                                            $mainItemTaxRate = ($data['tax_code_id'] ?? $record->tax_code_id) !== null
                                                ? (string) (int) round((float) ($data['tax_rate'] ?? $record->tax_rate ?? 0))
                                                : (string) (int) round((float) ($defaultTaxCode !== null ? $defaultTaxCode->rate : 0));
                                            $mainItemIsTaxInclusive = ($data['tax_code_id'] ?? $record->tax_code_id) !== null
                                                ? ($data['is_tax_inclusive'] ?? $record->is_tax_inclusive ?? false)
                                                : ($defaultTaxCode !== null && $defaultTaxCode->is_inclusive_default);

                                            $data['child_items'] = $childBuyerQuoteItems->map(function ($childItem) use ($mainItemTaxCodeId, $mainItemTaxRate, $mainItemIsTaxInclusive) {
                                                // Use stored tax information, or fallback to main item's tax
                                                $taxCodeId = $childItem->tax_code_id ?? $mainItemTaxCodeId;
                                                $taxRate = $childItem->tax_code_id !== null
                                                    ? (string) (int) round((float) $childItem->tax_rate)
                                                    : $mainItemTaxRate;
                                                $isTaxInclusive = $childItem->tax_code_id !== null
                                                    ? $childItem->is_tax_inclusive
                                                    : $mainItemIsTaxInclusive;

                                                return [
                                                    'request_item_id' => $childItem->request_item_id,
                                                    'supplier_quote_item_id' => $childItem->supplier_quote_item_id,
                                                    'description' => $childItem->description,
                                                    'quantity' => (string) (int) round((float) $childItem->quantity),
                                                    'unit_of_measure_id' => $childItem->unit_of_measure_id,
                                                    'cost_price' => (string) $childItem->cost_price,
                                                    'unit_price' => (string) $childItem->unit_price,
                                                    'unit_price_exc_tax' => (string) $childItem->unit_price_exc_tax,
                                                    'tax_code_id' => $taxCodeId,
                                                    'tax_rate' => $taxRate,
                                                    'is_tax_inclusive' => $isTaxInclusive,
                                                    'line_subtotal' => (string) $childItem->line_subtotal,
                                                    'line_tax' => (string) $childItem->line_tax,
                                                    'line_total' => (string) $childItem->line_total,
                                                    'hide_from_pdf' => $childItem->hide_from_pdf ?? false,
                                                ];
                                            })->values()->toArray();
                                        } else {
                                            $data['child_items'] = [];
                                        }
                                    }
                                }
                            }

                            return $data;
                        })
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                            // Always remove child_items from data before Filament tries to save it
                            // This prevents "Array to string conversion" error
                            unset($data['child_items']);

                            return $data;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                            // Always remove child_items from data before Filament tries to save it
                            // This prevents "Array to string conversion" error
                            unset($data['child_items']);

                            return $data;
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
                                        ->afterStateUpdated(function (Set $set, ?int $state) use ($request): void {
                                            if ($state === null) {
                                                return;
                                            }
                                            $requestItem = $request->items()->with('article.defaultTaxCode', 'unitOfMeasure')->find($state);
                                            if ($requestItem !== null) {
                                                $set('article_id', $requestItem->article_id);
                                                $set('description', $requestItem->description);
                                                $set('quantity', (string) (int) round((float) $requestItem->quantity));
                                                $set('unit_of_measure_id', $requestItem->unit_of_measure_id);

                                                // Prefill tax code from article's default tax code
                                                if ($requestItem->article?->default_tax_code_id !== null) {
                                                    $set('tax_code_id', $requestItem->article->default_tax_code_id);
                                                    $taxCode = $requestItem->article->defaultTaxCode;
                                                    if ($taxCode !== null) {
                                                        $set('tax_rate', (string) (int) round((float) $taxCode->rate));
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
                                        ->step(1)
                                        ->minValue(1)
                                        ->columnSpan(2)
                                        ->live(onBlur: true)
                                        ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (string) (int) round((float) $state) : null)
                                        ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (string) (int) round((float) $state) : '1')
                                        ->afterStateHydrated(function (Set $set, Get $get): void {
                                            $qty = $get('quantity');
                                            $normalized = $qty !== null && $qty !== '' ? (string) (int) round((float) $qty) : '1';
                                            $set('quantity', $normalized);
                                            $this->calculateItemTotals($set, $get);
                                        })
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
                                                $supplierQuoteItem = \App\Models\SupplierQuoteItem::with('supplierQuote.supplier')->find(\App\Support\SafeCast::toInt($supplierQuoteItemId));
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
                                                $marginPercent = (($unitPriceExcTax - $costPrice) / $costPrice) * 100;
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
                                        ->afterStateHydrated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get))
                                        ->afterStateUpdated(function (Set $set, Get $get): void {
                                            $costPrice = (float) ($get('cost_price') ?? 0);
                                            $unitPrice = (float) ($get('unit_price') ?? 0);

                                            // unit_price always represents the net price (Selling Price Net)
                                            $unitPriceExcTax = round($unitPrice, 0);

                                            // Update unit_price_exc_tax to match
                                            $set('unit_price_exc_tax', $unitPriceExcTax);

                                            if ($costPrice > 0 && $unitPriceExcTax > 0) {
                                                $marginPercent = (($unitPriceExcTax - $costPrice) / $costPrice) * 100;
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
                                                            $set('tax_rate', (string) (int) round((float) $taxCode->rate));
                                                            $set('is_tax_inclusive', $taxCode->is_inclusive_default);
                                                        }
                                                    }
                                                }
                                            }
                                        })
                                        ->afterStateUpdated(function (Set $set, Get $get, ?int $state): void {
                                            if ($state === null) {
                                                $set('tax_rate', '0');
                                            } else {
                                                $taxCode = TaxCode::find($state);
                                                if ($taxCode !== null) {
                                                    $set('tax_rate', (string) (int) round((float) $taxCode->rate));
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
                                        ->afterStateHydrated(function (Set $set, Get $get): void {
                                            $this->calculateItemTotals($set, $get);
                                            // Sync main item +Tax to child items on load and recalc their line totals
                                            $mainInclusive = $get('is_tax_inclusive');
                                            $mainInclusive = $mainInclusive === true || $mainInclusive === '1' || $mainInclusive === 1;
                                            $childItems = $get('child_items') ?? [];
                                            if (is_array($childItems) && $mainInclusive) {
                                                foreach (array_keys($childItems) as $index) {
                                                    $set("child_items.{$index}.is_tax_inclusive", true);
                                                    $this->syncChildItemLineTotals($set, $get, (int) $index, true);
                                                }
                                            }
                                        })
                                        ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                            $isTaxInclusive = (bool) $state;
                                            $this->calculateItemTotals($set, $get, $isTaxInclusive);
                                            // Sync +Tax to all child items and recalc their line totals
                                            $childItems = $get('child_items') ?? [];
                                            if (is_array($childItems)) {
                                                foreach (array_keys($childItems) as $index) {
                                                    $set("child_items.{$index}.is_tax_inclusive", $isTaxInclusive);
                                                    $this->syncChildItemLineTotals($set, $get, (int) $index, $isTaxInclusive);
                                                }
                                            }
                                        }),
                                    TextInput::make('tax_rate')
                                        ->label('Tax %')
                                        ->numeric()
                                        ->default(0)
                                        ->step(1)
                                        ->columnSpan(1)
                                        ->disabled()
                                        ->dehydrated()
                                        ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (string) (int) round((float) $state) : '0')
                                        ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (string) (int) round((float) $state) : '0'),
                                    // Margin %: default from general settings. Unit price = cost / (1 - margin%/100); margin% = (selling - cost)/selling×100. +Tax adds tax to line total.
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
                                            $defaultMargin = (float) ceil($this->currentTeam()?->getErpSettings()->default_margin_percent ?? 3.0);
                                            $stored = $get('margin_percent');
                                            $current = $get('margin_percent_input');
                                            $prefill = ($stored !== null && $stored !== '' && (float) $stored > 0)
                                                ? (int) round((float) $stored)
                                                : (int) $defaultMargin;
                                            if ($current === null || $current === '' || (float) $current === 0.0) {
                                                $set('margin_percent_input', $prefill);
                                            }
                                            $costPrice = (float) ($get('cost_price') ?? 0);
                                            $unitPrice = (float) ($get('unit_price') ?? 0);
                                            $unitPriceExcTaxStored = (float) ($get('unit_price_exc_tax') ?? 0);
                                            if ($unitPrice > 0) {
                                                $unitPriceExcTax = round($unitPrice, 0);
                                                $set('unit_price_exc_tax', $unitPriceExcTax);
                                            } elseif ($unitPriceExcTaxStored > 0) {
                                                $unitPriceExcTax = $unitPriceExcTaxStored;
                                            } else {
                                                $unitPriceExcTax = 0;
                                            }
                                            if ($costPrice > 0 && $unitPriceExcTax > 0) {
                                                $marginPercent = (($unitPriceExcTax - $costPrice) / $costPrice) * 100;
                                                $set('margin_percent_input', (int) round($marginPercent));
                                            }
                                            $this->calculateItemTotals($set, $get);
                                        })
                                        ->afterStateUpdated(function (Set $set, Get $get, ?float $state): void {
                                            $marginPercent = $state ?? 0;
                                            $costPrice = (float) ($get('cost_price') ?? 0);
                                            if ($costPrice > 0 && $marginPercent >= 0) {
                                                $unitPriceExcTax = round(MarginConvention::netUnitPrice($costPrice, $marginPercent), 0);
                                                $set('unit_price', $unitPriceExcTax);
                                                $set('unit_price_exc_tax', $unitPriceExcTax);
                                            }
                                            $this->calculateItemTotals($set, $get);
                                        })
                                        ->dehydrated(false),
                                    Hidden::make('line_total')->dehydrated(),
                                    Placeholder::make('line_total_display')
                                        ->label('Line Total')
                                        ->content(function (Get $get): string {
                                            $qty = (float) ($get('quantity') ?? 0);
                                            $price = (float) ($get('unit_price') ?? 0);
                                            $taxRate = (float) ($get('tax_rate') ?? 0);
                                            $incl = $get('is_tax_inclusive');
                                            $incl = $incl === true || $incl === '1' || $incl === 1;
                                            $sub = $qty * $price;
                                            if ($incl && $taxRate > 0) {
                                                return (string) round($sub + $sub * $taxRate / 100, 0);
                                            }

                                            return (string) round($sub, 0);
                                        }),
                                ]),
                            Checkbox::make('hide_from_pdf')
                                ->label('Hide from PDF')
                                ->helperText('This item will not appear in the PDF and its price will be distributed to visible items')
                                ->columnSpanFull(),
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
                                            Hidden::make('supplier_quote_item_id'),
                                            Grid::make(12)
                                                ->schema([
                                                    TextInput::make('description')
                                                        ->required()
                                                        ->columnSpan(4),
                                                    TextInput::make('quantity')
                                                        ->numeric()
                                                        ->required()
                                                        ->step(1)
                                                        ->minValue(1)
                                                        ->columnSpan(1)
                                                        ->live(onBlur: true)
                                                        ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (string) (int) round((float) $state) : null)
                                                        ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (string) (int) round((float) $state) : '1')
                                                        ->afterStateHydrated(function (Set $set, Get $get): void {
                                                            $qty = $get('quantity');
                                                            $normalized = $qty !== null && $qty !== '' ? (string) (int) round((float) $qty) : '1';
                                                            $set('quantity', $normalized);
                                                            $this->calculateItemTotals($set, $get);
                                                        })
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
                                                        ->columnSpan(3),
                                                    TextInput::make('cost_price')
                                                        ->label('Cost Price')
                                                        ->numeric()
                                                        ->default(0)
                                                        ->step(1) // No decimals for child items
                                                        ->columnSpan(4)
                                                        ->live(onBlur: true)
                                                        ->afterStateUpdated(function (Set $set, Get $get): void {
                                                            $costPrice = (float) ($get('cost_price') ?? 0);
                                                            $unitPrice = (float) ($get('unit_price') ?? 0);

                                                            // unit_price always represents the net price (Selling Price Net)
                                                            $unitPriceExcTax = round($unitPrice, 0);

                                                            // Update unit_price_exc_tax to match
                                                            $set('unit_price_exc_tax', $unitPriceExcTax);

                                                            if ($costPrice > 0 && $unitPriceExcTax > 0) {
                                                                $marginPercent = (($unitPriceExcTax - $costPrice) / $costPrice) * 100;
                                                            }

                                                            $this->calculateItemTotals($set, $get);
                                                        }),
                                                ]),
                                            Grid::make(12)
                                                ->schema([
                                                    TextInput::make('unit_price')
                                                        ->label('Selling Price (Net)')
                                                        ->numeric()
                                                        ->required()
                                                        ->default(0)
                                                        ->step(1) // No decimals for child items
                                                        ->columnSpan(3)
                                                        ->live(onBlur: true)
                                                        ->afterStateUpdated(function (Set $set, Get $get): void {
                                                            $costPrice = (float) ($get('cost_price') ?? 0);
                                                            $unitPrice = (float) ($get('unit_price') ?? 0);

                                                            $unitPriceExcTax = round($unitPrice, 0);
                                                            $set('unit_price_exc_tax', $unitPriceExcTax);

                                                            if ($costPrice > 0 && $unitPriceExcTax > 0) {
                                                                $marginPercent = (($unitPriceExcTax - $costPrice) / $costPrice) * 100;
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
                                                        ->columnSpan(3)
                                                        ->searchable()
                                                        ->live(onBlur: false)
                                                        ->afterStateUpdated(function (Set $set, Get $get, ?int $state): void {
                                                            if ($state === null) {
                                                                $set('tax_rate', '0');
                                                            } else {
                                                                $taxCode = TaxCode::find($state);
                                                                if ($taxCode !== null) {
                                                                    $set('tax_rate', (string) (int) round((float) $taxCode->rate));
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
                                                        ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                                            // Explicitly set the state and pass it directly to calculation
                                                            // This ensures correct behavior in nested Repeaters
                                                            $isTaxInclusive = (bool) $state;
                                                            $set('is_tax_inclusive', $isTaxInclusive);
                                                            $this->calculateItemTotals($set, $get, $isTaxInclusive);
                                                        }),
                                                    TextInput::make('tax_rate')
                                                        ->label('Tax %')
                                                        ->numeric()
                                                        ->default(0)
                                                        ->step(1)
                                                        ->columnSpan(1)
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? (string) (int) round((float) $state) : '0')
                                                        ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (string) (int) round((float) $state) : '0'),
                                                    TextInput::make('line_total')
                                                        ->label('Line Total')
                                                        ->numeric()
                                                        ->step(1) // No decimals for child items
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->columnSpan(4),
                                                ]),
                                            Checkbox::make('hide_from_pdf')
                                                ->label('Hide from PDF')
                                                ->helperText('This item will not appear in the PDF and its price will be distributed to visible items')
                                                ->columnSpanFull(),
                                        ])
                                        ->columns(1)
                                        ->defaultItems(0)
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => $state['description'] ?? null)
                                        ->dehydrated(true) // Include in form state
                                        ->visible(fn (Get $get): bool => $this->lineItemShowsChildren($get)),
                                ])
                                ->visible(fn (Get $get): bool => $this->lineItemShowsChildren($get))
                                ->collapsible(),
                        ])
                        ->columns(1)
                        ->defaultItems(0)
                        ->addActionLabel('Add Line Item')
                        ->reorderable()
                        ->orderColumn('sort_order')
                        ->collapsible()
                        ->live()
                        ->deletable(fn (?BuyerQuote $record): bool => ! $record instanceof \App\Models\BuyerQuote || $record->status->canEdit())
                        ->addable(false)
                        ->itemLabel(fn (array $state): ?string => $state['description'] ?? null),
                ])
                ->visible(fn (?Model $record, Get $get): bool => $this->showsSupplierQuoteDetails($record, $get)),

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
                ->visible(fn (?Model $record, Get $get): bool => $this->showsSupplierQuoteDetails($record, $get))
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
                ->visible(fn (?Model $record, Get $get): bool => $this->showsSupplierQuoteDetails($record, $get))
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
                        ->directory(BuyerQuote::PO_FILES_UPLOAD_DIRECTORY)
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
                            if ($record && $record->exists && $state && is_array($state)) {
                                foreach ($state as $file) {
                                    if (is_string($file)) {
                                        // Filament stores files relative to storage/app, so the path is already correct
                                        $filePath = storage_path('app/'.ltrim($file, '/'));

                                        if (file_exists($filePath)) {
                                            try {
                                                $media = $record->addMedia($filePath)
                                                    ->withCustomProperties([DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V2])
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

    /**
     * Build items, payment terms (and when single quote: currency, valid_until) from selected supplier quote(s).
     * When exactly one quote is selected: items and payment terms match that quote. When multiple: items combined, payment terms = team defaults.
     *
     * @param  array<int, int>  $supplierQuoteIds
     * @return array{items: array<int, array<string, mixed>>, paymentTerms: array<int, array<string, mixed>>, prepayment_type: string, prepayment_amount: int|float, currency_id?: int, valid_until?: \Illuminate\Support\Carbon, exchange_rate?: float}
     */
    private function buildFormDataFromSupplierQuotes(Request $request, array $supplierQuoteIds): array
    {
        /** @var \App\Models\Team|null $team */
        $team = Filament::getTenant();
        $settings = $team?->getErpSettings();
        $defaultCurrencyCode = $settings->default_currency ?? 'USD';
        $currencyId = (int) Currency::query()->where('code', $defaultCurrencyCode)->where('is_active', true)->value('id');
        $defaultTaxCode = TaxCode::query()
            ->where('team_id', $request->team_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
        $defaultMarginPercent = $settings->default_margin_percent ?? 3.0;
        $defaultPaymentTermsDays = $settings->default_payment_terms_days ?? 30;

        $singleQuote = null;
        if (count($supplierQuoteIds) === 1) {
            $singleQuote = SupplierQuote::query()
                ->where('request_id', $request->getKey())
                ->whereIn('id', $supplierQuoteIds)
                ->with(['paymentTerms', 'currency'])
                ->first();
        }

        $baseQuery = $this->supplierQuoteItemsForBuyerQuoteQuery($supplierQuoteIds)
            ->with(['supplierQuote.supplier', 'requestItem.article', 'requestItem.unitOfMeasure', 'requestItem.children']);

        $selectedSupplierQuoteItems = (clone $baseQuery)->get();

        $items = [];
        $sortOrder = 0;
        foreach ($selectedSupplierQuoteItems as $supplierQuoteItem) {
            $requestItem = $supplierQuoteItem->requestItem;
            if ($requestItem === null) {
                continue;
            }
            $costPrice = (float) $supplierQuoteItem->unit_price_exc_tax;
            $supplierQuoteItemId = $supplierQuoteItem->getKey();
            $supplierName = $supplierQuoteItem->supplierQuote->supplier->name ?? null;
            $mainItemTaxCode = $defaultTaxCode ?? $requestItem->article?->defaultTaxCode;
            $taxRate = $mainItemTaxCode !== null ? (float) $mainItemTaxCode->rate : 0.0;
            $addTax = $mainItemTaxCode !== null && $mainItemTaxCode->is_inclusive_default;
            $mainItemTaxCodeId = $mainItemTaxCode?->getKey();
            $quantity = (float) $requestItem->quantity;
            $unitPriceExcTax = $costPrice > 0 && $defaultMarginPercent < 100
                ? round($costPrice / (1 - $defaultMarginPercent / 100), 0)
                : 0.0;
            $unitPrice = round($unitPriceExcTax, 0);
            $lineSubtotal = $quantity * $unitPriceExcTax;
            if ($taxRate > 0) {
                $lineTax = $lineSubtotal * $taxRate / 100;
                $lineTotal = $addTax ? $lineSubtotal + $lineTax : $lineSubtotal;
            } else {
                $lineTax = 0;
                $lineTotal = $lineSubtotal;
            }
            $marginAmount = $unitPriceExcTax - $costPrice;
            $marginPercent = $unitPriceExcTax > 0 ? ($marginAmount / $unitPriceExcTax) * 100 : 0;

            $childItems = [];
            if ($requestItem->supportsItemHierarchy() && $requestItem->children()->exists()) {
                $childRequestItems = $requestItem->children()->orderBy('sort_order')->get();
                foreach ($childRequestItems as $childRequestItem) {
                    $childSupplierQuoteItem = \App\Models\SupplierQuoteItem::query()
                        ->whereIn('supplier_quote_id', $supplierQuoteIds)
                        ->where('request_item_id', $childRequestItem->getKey())
                        ->first();
                    $childQuantity = (float) $childRequestItem->quantity;
                    $childCostPrice = $childSupplierQuoteItem !== null ? (float) $childSupplierQuoteItem->unit_price_exc_tax : 0.0;
                    $childSupplierQuoteItemId = $childSupplierQuoteItem?->getKey();
                    if ($childSupplierQuoteItem === null) {
                        $anyChildQuoteItem = \App\Models\SupplierQuoteItem::query()
                            ->whereHas('supplierQuote', fn ($q) => $q->where('request_id', $request->getKey()))
                            ->where('request_item_id', $childRequestItem->getKey())
                            ->first();
                        if ($anyChildQuoteItem !== null) {
                            $childCostPrice = (float) $anyChildQuoteItem->unit_price_exc_tax;
                            $childSupplierQuoteItemId = $anyChildQuoteItem->getKey();
                        }
                    }
                    $childUnitPriceExcTax = $childCostPrice > 0 && $defaultMarginPercent < 100
                        ? round($childCostPrice / (1 - $defaultMarginPercent / 100), 0)
                        : 0.0;
                    $childUnitPrice = round($childUnitPriceExcTax, 0);
                    $childLineSubtotal = $childQuantity * $childUnitPriceExcTax;
                    $childLineTax = $taxRate > 0 && $addTax ? $childLineSubtotal * $taxRate / 100 : 0;
                    $childLineTotal = $addTax && $taxRate > 0 ? $childLineSubtotal + $childLineTax : $childLineSubtotal;
                    $childItems[] = [
                        'request_item_id' => $childRequestItem->getKey(),
                        'description' => $childRequestItem->description,
                        'quantity' => (string) (int) round((float) $childRequestItem->quantity),
                        'unit_of_measure_id' => $childRequestItem->unit_of_measure_id,
                        'cost_price' => (string) $childCostPrice,
                        'unit_price' => (string) $childUnitPrice,
                        'unit_price_exc_tax' => (string) round($childUnitPriceExcTax, 0),
                        'tax_code_id' => $mainItemTaxCodeId,
                        'tax_rate' => (string) (int) round((float) $taxRate),
                        'is_tax_inclusive' => $addTax,
                        'line_subtotal' => (string) round($childLineSubtotal, 4),
                        'line_tax' => (string) round($childLineTax, 4),
                        'line_total' => (string) round($childLineTotal, 0),
                        'supplier_quote_item_id' => $childSupplierQuoteItemId,
                        'hide_from_pdf' => false,
                    ];
                }
            }

            $items[] = [
                'request_item_id' => $requestItem->getKey(),
                'article_id' => $requestItem->article_id,
                'supplier_quote_item_id' => $supplierQuoteItemId,
                'from_supplier' => $supplierName,
                'description' => $requestItem->article !== null ? $requestItem->article->name : $requestItem->description,
                'quantity' => (string) (int) round((float) $requestItem->quantity),
                'unit_of_measure_id' => $requestItem->unit_of_measure_id,
                'unit' => $this->resolveUnitCode($requestItem),
                'cost_price' => (string) $costPrice,
                'unit_price' => (string) $unitPrice,
                'unit_price_exc_tax' => (string) round($unitPriceExcTax, 0),
                'tax_code_id' => $mainItemTaxCodeId,
                'tax_rate' => (string) (int) round((float) $taxRate),
                'tax_amount' => (string) round($lineTax / max($quantity, 0.0001), 4),
                'is_tax_inclusive' => $addTax,
                'line_subtotal' => (string) round($lineSubtotal, 4),
                'line_tax' => (string) round($lineTax, 4),
                'line_total' => (string) round($lineTotal, 0),
                'margin_amount' => (string) round($marginAmount, 4),
                'margin_percent' => (string) round($marginPercent, 4),
                'margin_percent_input' => (string) (int) ceil($defaultMarginPercent),
                'sort_order' => $sortOrder++,
                'child_items' => $childItems,
            ];
        }

        foreach ($items as &$item) {
            foreach ($item['child_items'] as &$childItem) {
                if (isset($childItem['tax_code_id'])) {
                    $taxCode = TaxCode::find(\App\Support\SafeCast::toInt($childItem['tax_code_id']));
                    if ($taxCode !== null) {
                        $childItem['tax_rate'] = (string) (int) round((float) $taxCode->rate);
                    }
                }
            }
        }
        unset($item, $childItem);

        if ($singleQuote !== null) {
            $paymentTerms = $singleQuote->paymentTerms->map(fn ($t) => [
                'due_days' => $t->due_days,
                'percentage' => $t->percentage,
                'job_progress' => $t->job_progress,
                'sort_order' => $t->sort_order,
            ])->values()->all();
            if ($paymentTerms === []) {
                $paymentTerms = [['due_days' => $defaultPaymentTermsDays, 'percentage' => 100, 'sort_order' => 0]];
            }
            // Use prepayment_percent when type is PERCENT; fall back to prepayment_amount when percent is 0 (legacy data)
            $prepaymentAmount = $singleQuote->prepayment_type === \App\Enums\PrepaymentType::PERCENT
                ? ((int) $singleQuote->prepayment_percent > 0 ? (int) $singleQuote->prepayment_percent : (int) round((float) $singleQuote->prepayment_amount))
                : (float) $singleQuote->prepayment_amount;
            $result = [
                'items' => $items,
                'paymentTerms' => $paymentTerms,
                'prepayment_type' => $singleQuote->prepayment_type->value,
                'prepayment_amount' => $prepaymentAmount,
                'currency_id' => $singleQuote->currency_id,
                'valid_until' => $singleQuote->valid_until ?? now()->addDays($settings->quote_validity_days ?? 30),
                'exchange_rate' => (float) $singleQuote->exchange_rate,
            ];
        } else {
            $result = [
                'items' => $items,
                'paymentTerms' => [['due_days' => $defaultPaymentTermsDays, 'percentage' => 100, 'sort_order' => 0]],
                'prepayment_type' => PrepaymentType::PERCENT->value,
                'prepayment_amount' => 0,
            ];
        }

        return $result;
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
                    ->label('New buyer quote')
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small)
                    ->modalWidth('7xl')
                    ->disabled(fn (): bool => ! $this->supplierQuotesAvailableForBuyerQuoteQuery($request)->exists())
                    ->fillForm(function (): array {
                        $settings = $this->currentTeam()?->getErpSettings();
                        $defaultPaymentTermsDays = $settings->default_payment_terms_days ?? 30;
                        $currencyId = (int) Currency::query()
                            ->where('code', $settings->default_currency ?? 'USD')
                            ->where('is_active', true)
                            ->value('id');

                        return [
                            'status' => BuyerQuoteStatus::DRAFT,
                            'currency_id' => $currencyId,
                            'exchange_rate' => 1,
                            'valid_until' => now()->addDays($settings->quote_validity_days ?? 30),
                            'prepayment_type' => PrepaymentType::PERCENT->value,
                            'prepayment_amount' => 0,
                            'paymentTerms' => [['due_days' => $defaultPaymentTermsDays, 'percentage' => 100, 'sort_order' => 0]],
                            'items' => [],
                        ];
                    })
                    ->using(function (array $data) use ($request): BuyerQuote {
                        $supplierQuoteIds = $this->resolveSupplierQuoteIdsFromData($data);
                        unset($data['creation_mode'], $data['supplier_quote_ids'], $data['supplier_quote_id']);

                        request()->attributes->set('_buyer_quote_create_supplier_quote_ids', $supplierQuoteIds);

                        return $request->buyerQuotes()->create(
                            collect($data)->except(['items', 'paymentTerms'])->toArray()
                        );
                    })
                    ->mutateFormDataUsing(function (array $data) use ($request): array {
                        $data['request_id'] = $request->getKey();
                        $data['buyer_id'] = $request->buyer_id;

                        $supplierQuoteIds = $this->resolveSupplierQuoteIdsFromData($data);
                        unset($data['creation_mode'], $data['supplier_quote_ids'], $data['supplier_quote_id'], $data['_supplier_quote_ids']);

                        request()->attributes->set('_buyer_quote_create_supplier_quote_ids', $supplierQuoteIds);

                        // When exactly one supplier quote selected, ensure prepayment comes from that quote (form may not have set it)
                        if (count($supplierQuoteIds) === 1) {
                            $sq = SupplierQuote::query()
                                ->where('request_id', $request->getKey())
                                ->whereKey($supplierQuoteIds[0])
                                ->first();
                            if ($sq !== null) {
                                $data['prepayment_type'] = $sq->prepayment_type->value;
                                $data['prepayment_amount'] = $sq->prepayment_type === PrepaymentType::PERCENT
                                    ? (string) ((int) $sq->prepayment_percent > 0 ? $sq->prepayment_percent : (int) round((float) $sq->prepayment_amount))
                                    : (string) $sq->prepayment_amount;
                            }
                        }

                        // Store child_items keyed by request_item_id for after(); remove from $data so not passed to create()
                        if (isset($data['items']) && is_array($data['items'])) {
                            $childItemsByRequestItemId = [];
                            foreach ($data['items'] as $index => $item) {
                                if (isset($item['child_items']) && is_array($item['child_items']) && ! empty($item['child_items'])) {
                                    $requestItemId = $item['request_item_id'] ?? null;
                                    if ($requestItemId !== null) {
                                        $childItemsByRequestItemId[$requestItemId] = $item['child_items'];
                                    }
                                }
                                unset($data['items'][$index]['child_items']);
                            }
                            if ($childItemsByRequestItemId !== []) {
                                request()->attributes->set('_buyer_quote_create_child_items', $childItemsByRequestItemId);
                            }
                        }
                        unset($data['_child_items']);

                        $this->validatePaymentTermsTotal($data, $request);

                        return $data;
                    })
                    ->after(function (BuyerQuote $record, array $data) use ($request): void {
                        // When exactly one supplier quote was selected, ensure payment terms are copied from it (safety net)
                        $supplierQuoteIds = request()->attributes->get('_buyer_quote_create_supplier_quote_ids', []);
                        if (is_array($supplierQuoteIds) && count($supplierQuoteIds) === 1) {
                            $supplierQuote = SupplierQuote::query()
                                ->where('request_id', $request->getKey())
                                ->whereKey($supplierQuoteIds[0])
                                ->with('paymentTerms')
                                ->first();
                            if ($supplierQuote !== null) {
                                $record->copyPaymentTermsFromSupplierQuote($supplierQuote);
                            }
                        }
                        request()->attributes->remove('_buyer_quote_create_supplier_quote_ids');

                        // Refresh media relationship to ensure it's loaded
                        $record->load('media');

                        // Get main items that were just created, keyed by request_item_id
                        $mainItems = $record->items()
                            ->whereNotNull('request_item_id')
                            ->whereHas('requestItem', fn ($q) => $q->whereNull('parent_id'))
                            ->orderBy('sort_order')
                            ->get()
                            ->keyBy('request_item_id');

                        // Process child items from form data (stored on request to avoid passing to create())
                        $dataChildItems = request()->attributes->get('_buyer_quote_create_child_items', []);
                        request()->attributes->remove('_buyer_quote_create_child_items');
                        $processedRequestItemIds = [];

                        // Get default tax code as fallback
                        $defaultTaxCode = TaxCode::query()
                            ->where('team_id', $request->team_id)
                            ->where('is_default', true)
                            ->where('is_active', true)
                            ->first();

                        if (is_array($dataChildItems) && $dataChildItems !== []) {
                            foreach ($dataChildItems as $requestItemId => $childItemsData) {
                                // Match by request_item_id instead of array index for reliability
                                $mainItem = $mainItems->get($requestItemId);

                                if ($mainItem !== null && is_array($childItemsData) && ! empty($childItemsData)) {
                                    foreach ($childItemsData as $childItemData) {
                                        $childRequestItemId = $childItemData['request_item_id'] ?? null;
                                        if ($childRequestItemId !== null && $record->items()->where('request_item_id', $childRequestItemId)->exists()) {
                                            continue;
                                        }

                                        $taxSettings = $this->resolveChildItemTaxSettings($childItemData, $mainItem, $defaultTaxCode);

                                        $childSortOrder = $mainItem->sort_order;
                                        $record->items()->create([
                                            'request_item_id' => $childItemData['request_item_id'] ?? null,
                                            'supplier_quote_item_id' => $childItemData['supplier_quote_item_id'] ?? null,
                                            'description' => $childItemData['description'] ?? '',
                                            'quantity' => $childItemData['quantity'] ?? '1.0000',
                                            'unit_of_measure_id' => $childItemData['unit_of_measure_id'] ?? null,
                                            'cost_price' => $childItemData['cost_price'] ?? '0.0000',
                                            'unit_price' => $childItemData['unit_price'] ?? '0.0000',
                                            'unit_price_exc_tax' => $childItemData['unit_price_exc_tax'] ?? '0.0000',
                                            'tax_code_id' => $taxSettings['tax_code_id'],
                                            'tax_rate' => $taxSettings['tax_rate'],
                                            'is_tax_inclusive' => $taxSettings['is_tax_inclusive'],
                                            'hide_from_pdf' => $childItemData['hide_from_pdf'] ?? false,
                                            'sort_order' => ++$childSortOrder,
                                        ]);
                                    }
                                    $processedRequestItemIds[] = $requestItemId;
                                }
                            }
                        }

                        // Fallback: If child items weren't created from form data, create them from RequestItem children
                        // This ensures child lines always exist for services main items with child RequestItems
                        if ($mainItems->isNotEmpty()) {
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

                            foreach ($mainItems as $mainItem) {
                                // Skip if already processed from form data
                                if (in_array($mainItem->request_item_id, $processedRequestItemIds)) {
                                    continue;
                                }

                                $requestItem = $request->items()->find($mainItem->request_item_id);
                                if ($requestItem === null || ! $requestItem->children()->exists()) {
                                    continue;
                                }

                                // Get child RequestItems
                                $childRequestItems = $requestItem->children()->get();
                                $sortOrderOffset = 1;

                                foreach ($childRequestItems as $childRequestItem) {
                                    // Check if child item already exists
                                    $existingChildItem = $record->items()
                                        ->where('request_item_id', $childRequestItem->id)
                                        ->first();

                                    if ($existingChildItem !== null) {
                                        continue; // Skip if already exists
                                    }

                                    // Find the corresponding supplier quote item for this child
                                    $childSupplierQuoteItem = \App\Models\SupplierQuoteItem::query()
                                        ->whereHas('supplierQuote', fn ($q) => $q->where('request_id', $request->getKey())
                                            ->where('status', SupplierQuoteStatus::SELECTED))
                                        ->where('request_item_id', $childRequestItem->getKey())
                                        ->first();

                                    $childCostPrice = $childSupplierQuoteItem !== null
                                        ? (float) $childSupplierQuoteItem->unit_price_exc_tax
                                        : 0.0;
                                    $childQuantity = (float) $childRequestItem->quantity;

                                    $childUnitPriceExcTax = $childCostPrice > 0 ? round(MarginConvention::netUnitPrice($childCostPrice, $defaultMarginPercent), 0) : 0.0;
                                    $childUnitPrice = round($childUnitPriceExcTax, 0);

                                    $childLineSubtotal = $childQuantity * $childUnitPriceExcTax;

                                    $childMarginAmount = $childUnitPriceExcTax - $childCostPrice;
                                    $childMarginPercent = $childCostPrice > 0 ? ($childMarginAmount / $childCostPrice) * 100 : 0;

                                    $mainTaxSettings = $this->resolveChildItemTaxSettings([], $mainItem, $defaultTaxCode);
                                    $childTaxRate = (float) $mainTaxSettings['tax_rate'];
                                    $childAddTax = $mainTaxSettings['is_tax_inclusive'];
                                    $childLineTax = $childTaxRate > 0 && $childAddTax ? $childLineSubtotal * $childTaxRate / 100 : 0;
                                    $childLineTotal = $childAddTax && $childTaxRate > 0 ? $childLineSubtotal + $childLineTax : $childLineSubtotal;

                                    $record->items()->create([
                                        'request_item_id' => $childRequestItem->getKey(),
                                        'supplier_quote_item_id' => $childSupplierQuoteItem?->getKey(),
                                        'description' => $childRequestItem->description,
                                        'quantity' => (string) $childRequestItem->quantity,
                                        'unit_of_measure_id' => $childRequestItem->unit_of_measure_id,
                                        'cost_price' => (string) $childCostPrice,
                                        'unit_price' => (string) $childUnitPrice,
                                        'unit_price_exc_tax' => (string) round($childUnitPriceExcTax, 0),
                                        'tax_code_id' => $mainTaxSettings['tax_code_id'],
                                        'tax_rate' => $mainTaxSettings['tax_rate'],
                                        'is_tax_inclusive' => $mainTaxSettings['is_tax_inclusive'],
                                        'line_subtotal' => (string) round($childLineSubtotal, 4),
                                        'line_tax' => (string) round($childLineTax, 4),
                                        'line_total' => (string) round($childLineTotal, 0),
                                        'margin_amount' => (string) round($childMarginAmount, 4),
                                        'margin_percent' => (string) round($childMarginPercent, 4),
                                        'hide_from_pdf' => false,
                                        'sort_order' => $mainItem->sort_order + $sortOrderOffset++,
                                    ]);
                                }
                            }
                        }

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
                            $selectedSupplierQuoteItems = $this->supplierQuoteItemsForBuyerQuoteQuery(
                                $request->supplierQuotes()
                                    ->where('request_id', $request->getKey())
                                    ->where('status', SupplierQuoteStatus::SELECTED)
                                    ->pluck('id')
                                    ->map(fn ($id): int => (int) $id)
                                    ->all()
                            )
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

                                // Unit price (Selling net) = Cost / (1 - margin%/100)
                                $unitPriceExcTax = $costPrice > 0
                                    ? round(MarginConvention::netUnitPrice($costPrice, $defaultMarginPercent), 4)
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

                                // Margin on cost: (selling - cost) / cost * 100
                                $marginAmount = $unitPriceExcTax - $costPrice;
                                $marginPercent = $costPrice > 0 ? ($marginAmount / $costPrice) * 100 : 0;

                                $record->items()->create([
                                    'request_item_id' => $requestItem->getKey(),
                                    'article_id' => $requestItem->article_id,
                                    'supplier_quote_item_id' => $supplierQuoteItemId,
                                    'description' => $requestItem->article !== null ? $requestItem->article->name : $requestItem->description,
                                    'quantity' => $requestItem->quantity,
                                    'unit_of_measure_id' => $requestItem->unit_of_measure_id,
                                    'unit' => $this->resolveUnitCode($requestItem),
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

                        // Remove invalid orphan line items and re-link PNL when the linked quote was deleted
                        $this->syncChildItemTaxFromParents($record);
                        $record->items()->whereNull('request_item_id')->delete();

                        $pnl = \App\Models\ProfitAndLoss::query()
                            ->where('request_id', $request->getKey())
                            ->latest()
                            ->first();
                        if ($pnl !== null) {
                            $linkedQuote = $pnl->buyer_quote_id !== null
                                ? BuyerQuote::withTrashed()->find($pnl->buyer_quote_id)
                                : null;
                            if ($linkedQuote === null || $linkedQuote->trashed()) {
                                $pnl->buyer_quote_id = $record->getKey();
                                $pnl->saveQuietly();
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

                        $team = $request->team;

                        // Prepared By: key account assigned to this request's buyer (same team)
                        $preparedByDefault = $team && $request->buyer_id
                            ? User::query()
                                ->whereHas('teams', fn ($q) => $q->where('teams.id', $team->id)
                                    ->where('team_user.role', 'central_purchasing')
                                    ->where('team_user.central_purchasing_role', CentralPurchasingRole::KEY_ACCOUNT->value))
                                ->whereHas('buyers', fn ($q) => $q->where('companies.id', $request->buyer_id))
                                ->orderBy('name')
                                ->first()
                                ?->id
                            : null;

                        $deptHeadDefault = $team
                            ? TeamMemberService::getTeamMembersByCentralPurchasingRole($team, CentralPurchasingRole::DEPT_HEAD_SALES)->first()?->id
                            : null;
                        $deputyDirectorDefault = $team
                            ? TeamMemberService::getTeamMembersByCentralPurchasingRole($team, CentralPurchasingRole::DEPUTY_DIRECTOR)->first()?->id
                            : null;
                        $directorDefault = $team
                            ? TeamMemberService::getTeamMembersByCentralPurchasingRole($team, CentralPurchasingRole::DIRECTOR)->first()?->id
                            : null;

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
                                        ->columnSpanFull()
                                        ->default($request->description ?? $request->title),
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
                                    )
                                        ->default($preparedByDefault),
                                    KeyAccountSelect::makeWithRelationship(
                                        'dept_head_sales_id',
                                        'Dept Head of Sales',
                                        'deptHeadSales',
                                        CentralPurchasingRole::DEPT_HEAD_SALES,
                                        null
                                    )
                                        ->default($deptHeadDefault),
                                    KeyAccountSelect::makeWithRelationship(
                                        'deputy_director_id',
                                        'Deputy Director',
                                        'deputyDirector',
                                        CentralPurchasingRole::DEPUTY_DIRECTOR,
                                        null
                                    )
                                        ->default($deputyDirectorDefault),
                                    KeyAccountSelect::makeWithRelationship(
                                        'approved_by_id',
                                        'Approved By',
                                        'approvedBy',
                                        CentralPurchasingRole::DIRECTOR,
                                        null
                                    )
                                        ->default($directorDefault),
                                ])
                                ->columns(2),
                        ];
                    })
                    ->action(function (array $data) use ($request): void {
                        // Link PNL to the latest valid buyer quote at creation time
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
                    ->authorize(function () use ($request): bool {
                        $draftQuote = $request->buyerQuotes()
                            ->where('status', BuyerQuoteStatus::DRAFT)
                            ->latest()
                            ->first();

                        return $draftQuote !== null && auth()->user()?->can('send', $draftQuote) === true;
                    })
                    ->visible(function () use ($request): bool {
                        if (! $request->buyerQuotes()->where('status', BuyerQuoteStatus::DRAFT)->exists()) {
                            return false;
                        }
                        /** @var \App\Models\ProfitAndLoss|null $latestPNL */
                        $latestPNL = $request->profitAndLosses()->latest()->first();

                        return $latestPNL !== null && $latestPNL->status->isApproved();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Send draft quote(s)?')
                    ->modalDescription(function () use ($request): string {
                        $sendableQuotes = $request->buyerQuotes()->where('status', BuyerQuoteStatus::DRAFT)->orderBy('id')->get();
                        if ($sendableQuotes->isEmpty()) {
                            return 'No draft buyer quotes to send.';
                        }
                        $lines = ['This will mark the following quote(s) as sent and set the issue date to today:'];
                        foreach ($sendableQuotes as $bq) {
                            $email = $bq->buyer->email ?? null;
                            $name = $bq->buyer->name ?? 'Unknown';
                            $quoteLabel = $bq->quote_number ?? 'Quote #'.$bq->id;
                            if (empty($email)) {
                                $lines[] = "• **{$quoteLabel}** — ⚠️ No email configured for {$name}; will be marked as sent only.";
                            } else {
                                $lines[] = "• **{$quoteLabel}** → {$email}";
                            }
                        }

                        return implode("\n\n", $lines);
                    })
                    ->action(function () use ($request): void {
                        $sendableQuotes = $request->buyerQuotes()->where('status', BuyerQuoteStatus::DRAFT)->orderBy('id')->get();
                        if ($sendableQuotes->isEmpty()) {
                            Notification::make()
                                ->title('No quote found')
                                ->body('No draft buyer quotes to send.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $emailService = app(\App\Services\Email\EmailTemplateService::class);
                        $sentCount = 0;
                        $emailFailures = [];

                        foreach ($sendableQuotes as $buyerQuote) {
                            $buyerQuote->markAsSent();
                            $buyerEmail = $buyerQuote->buyer->email ?? null;
                            $buyerName = $buyerQuote->buyer->name ?? 'Buyer';
                            $quoteLabel = $buyerQuote->quote_number ?? 'Quote #'.$buyerQuote->id;

                            if (empty($buyerEmail)) {
                                $sentCount++;

                                continue;
                            }

                            try {
                                $settings = $buyerQuote->team->getErpSettings();
                                $emailService->sendWithTeamSettings(
                                    $buyerQuote->team,
                                    new \App\Mail\Erp\QuoteToBuyerMail($buyerQuote),
                                    $buyerEmail,
                                    $settings->email_template_buyer_quote,
                                    $settings->email_template_buyer_quote_id ?? null,
                                    \App\Models\EmailTemplate::TYPE_BUYER_QUOTE
                                );
                                $sentCount++;
                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\Log::error('Failed to send buyer quote email', [
                                    'quote_id' => $buyerQuote->id,
                                    'buyer_email' => $buyerEmail,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);
                                $emailFailures[] = "{$quoteLabel}: ".$e->getMessage();
                            }
                        }

                        $total = $sendableQuotes->count();
                        if ($emailFailures === []) {
                            Notification::make()
                                ->title($total === 1 ? 'Quote sent' : 'Quotes sent')
                                ->body($total === 1
                                    ? 'Quote has been sent successfully.'
                                    : "{$sentCount} of {$total} quote(s) have been sent successfully.")
                                ->success()
                                ->send();
                        } else {
                            $failureList = implode("\n", array_slice($emailFailures, 0, 5));
                            if (count($emailFailures) > 5) {
                                $failureList .= "\n... and ".(count($emailFailures) - 5).' more.';
                            }
                            Notification::make()
                                ->title('Sent with errors')
                                ->body("All {$total} quote(s) were marked as sent. {$sentCount} email(s) sent. Failed to send email:\n\n".$failureList)
                                ->warning()
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
                        ->fillForm(function (BuyerQuote $record): array {
                            $prepaymentAmount = $record->prepayment_type === PrepaymentType::PERCENT
                                ? ((int) $record->prepayment_percent > 0 ? (string) $record->prepayment_percent : (string) (int) round((float) $record->prepayment_amount))
                                : (string) (int) round((float) $record->prepayment_amount);

                            return [
                                'status' => $record->status,
                                'currency_id' => $record->currency_id,
                                'valid_until' => $record->valid_until,
                                'exchange_rate' => $record->exchange_rate ?? 1,
                                'prepayment_type' => $record->prepayment_type,
                                'prepayment_amount' => $prepaymentAmount,
                            ];
                        })
                        ->mutateFormDataUsing(function (array $data): array {
                            $this->validatePaymentTermsTotal($data, $this->getOwnerRequest());

                            // Ensure unit_price_exc_tax matches unit_price for all items (both should be net price)
                            if (isset($data['items']) && is_array($data['items'])) {
                                foreach ($data['items'] as $key => $item) {
                                    if (isset($item['unit_price']) && (float) $item['unit_price'] > 0) {
                                        $data['items'][$key]['unit_price_exc_tax'] = round((float) $item['unit_price'], 0);
                                    }
                                }
                            }

                            // Store child_items on request attributes; Filament does not pass _child_items to after()
                            if (isset($data['items']) && is_array($data['items'])) {
                                $childItemsByRequestItemId = [];
                                foreach ($data['items'] as $index => $item) {
                                    if (isset($item['child_items']) && is_array($item['child_items']) && ! empty($item['child_items'])) {
                                        $requestItemId = $item['request_item_id'] ?? null;
                                        if ($requestItemId !== null) {
                                            $childItemsByRequestItemId[$requestItemId] = $item['child_items'];
                                        }
                                    }
                                    unset($data['items'][$index]['child_items']);
                                }
                                if ($childItemsByRequestItemId !== []) {
                                    request()->attributes->set('_buyer_quote_edit_child_items', $childItemsByRequestItemId);
                                }
                            }
                            unset($data['_child_items']);

                            return $data;
                        })
                        ->after(function (BuyerQuote $record, array $data): void {
                            $dataChildItems = request()->attributes->get('_buyer_quote_edit_child_items', []);
                            request()->attributes->remove('_buyer_quote_edit_child_items');

                            if (is_array($dataChildItems) && $dataChildItems !== []) {
                                $record->loadMissing('request');
                                $defaultTaxCode = TaxCode::query()
                                    ->where('team_id', $record->request->team_id)
                                    ->where('is_default', true)
                                    ->where('is_active', true)
                                    ->first();

                                $mainItems = $record->items()
                                    ->whereNotNull('request_item_id')
                                    ->whereHas('requestItem', fn ($q) => $q->whereNull('parent_id'))
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->keyBy('request_item_id');

                                foreach ($dataChildItems as $requestItemId => $childItemsData) {
                                    $mainItem = $mainItems->get($requestItemId);

                                    if ($mainItem === null || ! is_array($childItemsData) || $childItemsData === []) {
                                        continue;
                                    }

                                    $childRequestItemIds = $mainItem->requestItem?->children()->pluck('id')->toArray() ?? [];
                                    if ($childRequestItemIds !== []) {
                                        $record->items()
                                            ->whereIn('request_item_id', $childRequestItemIds)
                                            ->delete();
                                    }

                                    $childSortOrder = $mainItem->sort_order;
                                    foreach ($childItemsData as $childItemData) {
                                        $taxSettings = $this->resolveChildItemTaxSettings($childItemData, $mainItem, $defaultTaxCode);

                                        $record->items()->create([
                                            'request_item_id' => $childItemData['request_item_id'] ?? null,
                                            'supplier_quote_item_id' => $childItemData['supplier_quote_item_id'] ?? null,
                                            'description' => $childItemData['description'] ?? '',
                                            'quantity' => $childItemData['quantity'] ?? '1.0000',
                                            'unit_of_measure_id' => $childItemData['unit_of_measure_id'] ?? null,
                                            'cost_price' => $childItemData['cost_price'] ?? '0.0000',
                                            'unit_price' => $childItemData['unit_price'] ?? '0.0000',
                                            'unit_price_exc_tax' => $childItemData['unit_price_exc_tax'] ?? '0.0000',
                                            'tax_code_id' => $taxSettings['tax_code_id'],
                                            'tax_rate' => $taxSettings['tax_rate'],
                                            'is_tax_inclusive' => $taxSettings['is_tax_inclusive'],
                                            'hide_from_pdf' => $childItemData['hide_from_pdf'] ?? false,
                                            'sort_order' => ++$childSortOrder,
                                        ]);
                                    }
                                }
                            }

                            $this->syncChildItemTaxFromParents($record);

                            // Ensure all items have correct unit_price_exc_tax after save
                            $record->load('items');
                            foreach ($record->items as $item) {
                                $needsRecalculate = ((float) $item->unit_price !== (float) $item->unit_price_exc_tax && (float) $item->unit_price > 0)
                                    || ($item->is_tax_inclusive && (float) $item->tax_rate > 0 && (float) $item->line_tax <= 0);

                                if ($needsRecalculate) {
                                    $item->recalculatePrices();
                                    $item->saveQuietly();
                                }
                            }
                            // Recalculate quote totals
                            $record->recalculateTotals();

                            // Remove invalid orphan line items (no request_item_id)
                            $record->items()->whereNull('request_item_id')->delete();
                        }),
                    \Filament\Actions\DeleteAction::make()
                        ->hidden(function (?BuyerQuote $record): bool {
                            if ($record === null) {
                                return true;
                            }
                            // Override default trashed check to also check status
                            if ($record->trashed()) {
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
                        ->authorize(fn (?BuyerQuote $record): bool => $record !== null && auth()->user()?->can('update', $record) === true)
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
                        ->authorize(fn (?BuyerQuote $record): bool => $record !== null && auth()->user()?->can('update', $record) === true)
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
                        ->visible(function (?BuyerQuote $record): bool {
                            if ($record === null) {
                                return false;
                            }
                            // Don't show for superseded quotes
                            if ($record->status === BuyerQuoteStatus::SUPERSEDED) {
                                return false;
                            }

                            // Only show if there are additional items available
                            return $record->hasAdditionalItems();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Create new version with additional items?')
                        ->modalDescription('This will create a new draft version of this quote including any additional items that have been added to the request. The current quote will be marked as superseded, and any approved PNL will be reset to pending.')
                        ->action(function (BuyerQuote $record): void {
                            $newQuote = $record->createNewVersion();
                            $additionalItemsCount = $newQuote->items()->count() - $record->items()->count();

                            $message = "Version {$newQuote->version} has been created as a draft.";
                            if ($additionalItemsCount > 0) {
                                $message .= " {$additionalItemsCount} additional item(s) have been added.";
                            }

                            Notification::make()
                                ->title('New version created')
                                ->body($message)
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

    private function isCreatingBuyerQuote(?Model $record): bool
    {
        return $record === null || ! $record->exists;
    }

    /**
     * The owner Request record, typed precisely (Filament's RelationManager only
     * declares this as the generic Eloquent Model).
     */
    private function getOwnerRequest(): Request
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $request;
    }

    /**
     * The current tenant Team, typed precisely (Filament's Filament::getTenant()
     * only declares this as the generic Eloquent Model).
     */
    private function currentTeam(): ?\App\Models\Team
    {
        /** @var \App\Models\Team|null $team */
        $team = Filament::getTenant();

        return $team;
    }

    /**
     * Resolve the display unit code for a request item: prefer the linked
     * UnitOfMeasure's code, then fall back to the legacy Unit enum value.
     */
    private function resolveUnitCode(\App\Models\RequestItem $requestItem): string
    {
        $unitOfMeasure = $requestItem->unitOfMeasure;
        if ($unitOfMeasure !== null) {
            return $unitOfMeasure->code;
        }

        return $requestItem->unit instanceof \App\Enums\Unit ? $requestItem->unit->value : 'pcs';
    }

    private function normalizeCreationMode(mixed $value): ?BuyerQuoteCreationMode
    {
        if ($value instanceof BuyerQuoteCreationMode) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return BuyerQuoteCreationMode::tryFrom($value);
        }

        return null;
    }

    private function hasCreationModeSelected(Get $get): bool
    {
        return $this->normalizeCreationMode($get('creation_mode')) !== null;
    }

    private function isConsolidatedMode(Get $get): bool
    {
        return $this->normalizeCreationMode($get('creation_mode')) === BuyerQuoteCreationMode::CONSOLIDATED;
    }

    private function isPerSupplierMode(Get $get): bool
    {
        return $this->normalizeCreationMode($get('creation_mode')) === BuyerQuoteCreationMode::PER_SUPPLIER;
    }

    private function showsSupplierQuoteDetails(?Model $record, Get $get): bool
    {
        if ($record !== null && $record->exists) {
            return true;
        }

        if (! $this->hasCreationModeSelected($get)) {
            return false;
        }

        if ($this->isPerSupplierMode($get)) {
            return filled($get('supplier_quote_id'));
        }

        $ids = $get('supplier_quote_ids');

        return is_array($ids) && $ids !== [];
    }

    /**
     * Supplier quotes eligible for buyer quote creation (comparison-selected or obtained).
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\SupplierQuote>
     */
    private function supplierQuotesAvailableForBuyerQuoteQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return SupplierQuote::query()
            ->where('request_id', $request->getKey())
            ->where('status', SupplierQuoteStatus::SELECTED)
            ->where(function ($query): void {
                $query->where('obtained', true)
                    ->orWhereHas('items', fn ($q) => $q->where('is_selected', true));
            });
    }

    /**
     * Supplier quote line items to include when building a buyer quote.
     *
     * @param  array<int, int>  $supplierQuoteIds
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\SupplierQuoteItem>
     */
    private function supplierQuoteItemsForBuyerQuoteQuery(array $supplierQuoteIds): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Models\SupplierQuoteItem::query()
            ->whereIn('supplier_quote_id', $supplierQuoteIds)
            ->whereHas('requestItem', fn ($q) => $q->whereNull('parent_id'))
            ->where(function ($query): void {
                $query->where('is_selected', true)
                    ->orWhereHas('supplierQuote', fn ($q) => $q->where('obtained', true));
            });
    }

    /**
     * @return array<int, string>
     */
    private function getSupplierQuoteOptions(Request $request): array
    {
        return $this->supplierQuotesAvailableForBuyerQuoteQuery($request)
            ->orderBy('quote_number')
            ->get()
            ->mapWithKeys(fn (SupplierQuote $sq): array => [
                $sq->getKey() => $sq->quote_number,
            ])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function resolveSupplierQuoteIdsFromGet(Get $get): array
    {
        if ($this->isPerSupplierMode($get)) {
            $supplierQuoteId = $get('supplier_quote_id');

            return is_numeric($supplierQuoteId) ? [(int) $supplierQuoteId] : [];
        }

        $ids = $get('supplier_quote_ids');

        return is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function resolveSupplierQuoteIdsFromData(array $data): array
    {
        $mode = $this->normalizeCreationMode($data['creation_mode'] ?? null);

        if ($mode === BuyerQuoteCreationMode::PER_SUPPLIER) {
            $supplierQuoteId = $data['supplier_quote_id'] ?? null;

            return is_numeric($supplierQuoteId) ? [(int) $supplierQuoteId] : [];
        }

        if ($mode === BuyerQuoteCreationMode::CONSOLIDATED) {
            $ids = $data['supplier_quote_ids'] ?? [];

            return is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
        }

        return [];
    }

    private function resetSupplierQuoteFormData(Set $set): void
    {
        $settings = $this->currentTeam()?->getErpSettings();
        $defaultPaymentTermsDays = $settings->default_payment_terms_days ?? 30;
        $set('items', []);
        $set('paymentTerms', [['due_days' => $defaultPaymentTermsDays, 'percentage' => 100, 'sort_order' => 0]]);
        $set('prepayment_type', PrepaymentType::PERCENT->value);
        $set('prepayment_amount', 0);
    }

    /**
     * @return array<int, int>
     */
    private function getSelectedSupplierQuoteIds(Request $request): array
    {
        return $this->supplierQuotesAvailableForBuyerQuoteQuery($request)
            ->orderBy('quote_number')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $supplierQuoteIds
     */
    private function applyBuiltSupplierQuoteData(Set $set, Request $request, array $supplierQuoteIds): void
    {
        if ($supplierQuoteIds === []) {
            $settings = $this->currentTeam()?->getErpSettings();
            $defaultPaymentTermsDays = $settings->default_payment_terms_days ?? 30;
            $set('items', []);
            $set('paymentTerms', [['due_days' => $defaultPaymentTermsDays, 'percentage' => 100, 'sort_order' => 0]]);
            $set('prepayment_type', PrepaymentType::PERCENT->value);
            $set('prepayment_amount', 0);

            return;
        }

        $built = $this->buildFormDataFromSupplierQuotes($request, $supplierQuoteIds);
        $set('items', $built['items']);
        $set('paymentTerms', $built['paymentTerms']);
        if (isset($built['currency_id'])) {
            $set('currency_id', $built['currency_id']);
        }
        if (isset($built['valid_until'])) {
            $set('valid_until', $built['valid_until']);
        }
        if (isset($built['exchange_rate'])) {
            $set('exchange_rate', $built['exchange_rate']);
        }
        $set('prepayment_type', $built['prepayment_type']);
        $prepaymentValue = is_int($built['prepayment_amount']) || is_float($built['prepayment_amount'])
            ? (string) $built['prepayment_amount']
            : $built['prepayment_amount'];
        $set('prepayment_amount', $prepaymentValue);
    }

    /**
     * @param  array<string, mixed>  $childItemData
     * @return array{tax_code_id: int|null, tax_rate: string, is_tax_inclusive: bool}
     */
    private function resolveChildItemTaxSettings(array $childItemData, BuyerQuoteItem $mainItem, ?TaxCode $defaultTaxCode): array
    {
        $childTaxCodeId = $childItemData['tax_code_id'] ?? null;
        if ($childTaxCodeId === '' || $childTaxCodeId === '0') {
            $childTaxCodeId = null;
        }

        $taxCodeId = $childTaxCodeId ?? $mainItem->tax_code_id ?? $defaultTaxCode?->getKey();
        $inheritsFromMain = $childTaxCodeId === null
            || (int) $childTaxCodeId === (int) $mainItem->tax_code_id;

        if ($inheritsFromMain && $mainItem->tax_code_id !== null) {
            return [
                'tax_code_id' => $taxCodeId,
                'tax_rate' => (string) (int) round((float) $mainItem->tax_rate),
                'is_tax_inclusive' => $mainItem->is_tax_inclusive,
            ];
        }

        if ($taxCodeId === null) {
            return [
                'tax_code_id' => null,
                'tax_rate' => '0',
                'is_tax_inclusive' => false,
            ];
        }

        $taxRate = isset($childItemData['tax_rate']) && $childItemData['tax_rate'] !== ''
            ? (string) (int) round((float) $childItemData['tax_rate'])
            : (string) (int) round((float) ($defaultTaxCode !== null ? $defaultTaxCode->rate : 0));

        return [
            'tax_code_id' => $taxCodeId,
            'tax_rate' => $taxRate,
            'is_tax_inclusive' => filter_var($childItemData['is_tax_inclusive'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function syncChildItemTaxFromParents(BuyerQuote $record): void
    {
        $record->syncChildItemTaxFromParents();
    }

    /**
     * Validate that payment terms percentages (and prepayment when type is percentage) sum to 100%.
     *
     * When prepayment type is Percentage and prepayment > 0: prepayment % + sum(terms %) must equal 100%.
     * When prepayment type is Fixed Amount or prepayment is 0: sum(terms %) must equal 100%.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function validatePaymentTermsTotal(array $data, Request $request): void
    {
        $buyer = $request->buyer;
        if (! $buyer?->credit_status) {
            return;
        }

        $terms = $data['paymentTerms'] ?? [];
        if (! is_array($terms)) {
            return;
        }

        $termsSum = 0.0;
        $termCount = 0;
        foreach ($terms as $item) {
            $pct = (float) ($item['percentage'] ?? 0);
            if ($pct > 0) {
                $termsSum += $pct;
                $termCount++;
            }
        }

        if ($termCount <= 1) {
            return;
        }

        $prepaymentType = $data['prepayment_type'] ?? null;
        if ($prepaymentType instanceof \BackedEnum) {
            $prepaymentType = $prepaymentType->value;
        }
        $prepaymentAmount = (float) ($data['prepayment_amount'] ?? 0);
        $isPercentPrepayment = $prepaymentType === PrepaymentType::PERCENT->value && $prepaymentAmount > 0;

        $requiredTotal = 100.0;
        $actualTotal = $termsSum;
        if ($isPercentPrepayment) {
            $actualTotal = $prepaymentAmount + $termsSum;
        }

        if (abs($actualTotal - $requiredTotal) <= 0.01) {
            return;
        }

        if ($isPercentPrepayment) {
            throw ValidationException::withMessages([
                'paymentTerms' => sprintf(
                    'The total payment terms percentage including prepayment must equal 100%%. Prepayment: %s%%, Payment terms: %s%%. Current total: %s%%.',
                    number_format($prepaymentAmount, 2),
                    number_format($termsSum, 2),
                    number_format($actualTotal, 2)
                ),
            ]);
        }

        throw ValidationException::withMessages([
            'paymentTerms' => sprintf(
                'The total payment terms percentage must equal 100%%. Current total: %s%%.',
                number_format($termsSum, 2)
            ),
        ]);
    }

    /**
     * Recalculate and set line_subtotal, line_tax, line_total for one child item (used when parent +Tax is synced).
     */
    private function syncChildItemLineTotals(Set $set, Get $get, int $childIndex, bool $isTaxInclusive): void
    {
        $prefix = "child_items.{$childIndex}.";
        $quantity = (float) ($get($prefix.'quantity') ?? 0);
        $unitPrice = (float) ($get($prefix.'unit_price') ?? 0);
        $unitPriceExcTaxStored = (float) ($get($prefix.'unit_price_exc_tax') ?? 0);
        $unitPriceExcTax = $unitPrice > 0 ? round($unitPrice, 0) : ($unitPriceExcTaxStored > 0 ? $unitPriceExcTaxStored : 0);
        $taxRate = (float) ($get($prefix.'tax_rate') ?? 0);

        // Same shared LineCalculator as the parent-item preview and the observer.
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: $unitPriceExcTax,
            priceBasis: PriceBasis::NET,
            taxable: $isTaxInclusive && $taxRate > 0,
            taxRate: $taxRate,
            quantity: $quantity,
            currencyDecimals: 0,
        );

        $set($prefix.'line_subtotal', $amounts->lineSubtotal);
        $set($prefix.'line_tax', $amounts->lineTax);
        $set($prefix.'line_total', $amounts->lineTotal);
    }

    /**
     * Calculate item totals based on form values.
     *
     * When is_tax_inclusive is true, unit_price includes tax.
     * When is_tax_inclusive is false, unit_price is net price and tax is added on top.
     *
     * @param  bool|null  $isTaxInclusiveOverride  Optional override for is_tax_inclusive value (useful for nested Repeaters)
     */
    private function calculateItemTotals(Set $set, Get $get, ?bool $isTaxInclusiveOverride = null): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $unitPriceExcTaxStored = (float) ($get('unit_price_exc_tax') ?? 0);
        $costPrice = (float) ($get('cost_price') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);
        $taxInclusiveRaw = $get('is_tax_inclusive');
        $isTaxInclusive = $isTaxInclusiveOverride !== null ? $isTaxInclusiveOverride : ($taxInclusiveRaw === true || $taxInclusiveRaw === '1' || $taxInclusiveRaw === 1);

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

        // Line totals via the shared LineCalculator (same engine the observer uses
        // on save), so the live preview matches the persisted values exactly. Buyer
        // items are net; the "+ Tax" checkbox decides whether tax is added on top.
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: $unitPriceExcTax,
            priceBasis: PriceBasis::NET,
            taxable: $isTaxInclusive && $taxRate > 0,
            taxRate: $taxRate,
            quantity: $quantity,
            currencyDecimals: 0,
        );

        // Margin shown in the quote form is markup-on-cost (the quoting convention),
        // distinct from the P&L's gross-margin-on-selling.
        $marginAmount = $unitPriceExcTax - $costPrice;
        $marginPercent = $costPrice > 0 ? ($marginAmount / $costPrice) * 100 : 0;

        $set('unit_price_exc_tax', round($unitPriceExcTax, 0));
        $set('line_subtotal', $amounts->lineSubtotal);
        $set('line_tax', $amounts->lineTax);
        $set('line_total', $amounts->lineTotal);
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

    /**
     * Memoized ids of the owner request's services items (per Livewire render).
     *
     * @var list<int>|null
     */
    private ?array $serviceItemIds = null;

    /**
     * Whether a quote line's child-items section should show: the line already
     * carries child data, or its request item is a services item.
     */
    private function lineItemShowsChildren(Get $get): bool
    {
        if (! empty($get('child_items'))) {
            return true;
        }

        $requestItemId = $get('request_item_id');
        if ($requestItemId === null) {
            return false;
        }

        if ($this->serviceItemIds === null) {
            /** @var Request $request */
            $request = $this->getOwnerRecord();

            /** @var list<int> $serviceItemIds */
            $serviceItemIds = $request->items()
                ->where('item_type', \App\Enums\ItemType::SERVICE)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();
            $this->serviceItemIds = $serviceItemIds;
        }

        return in_array((int) $requestItemId, $this->serviceItemIds, true);
    }
}
