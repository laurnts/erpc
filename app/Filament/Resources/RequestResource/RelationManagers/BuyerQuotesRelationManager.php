<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\BuyerQuoteStatus;
use App\Enums\CentralPurchasingRole;
use App\Enums\PNLStatus;
use App\Enums\PrepaymentType;
use App\Enums\RequestStage;
use App\Enums\SupplierQuoteStatus;
use App\Filament\Actions\DownloadPdfAction;
use App\Filament\Forms\Components\KeyAccountSelect;
use App\Filament\Forms\Components\SafeLabelRepeater;
use App\Filament\Resources\ProfitAndLossResource;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\BuyerQuotePaymentTerm;
use App\Models\Currency;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\SupplierQuotePaymentTerm;
use App\Support\Media\DocumentPathGenerator;
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
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Closure;

final class BuyerQuotesRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'buyerQuotes';

    protected static ?string $title = 'Buyer Quotes';

    /** Unused; kept for Livewire state compatibility (was used by chunked loading). */
    public int $step2TotalGroups = 0;

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
     * Recursively make array safe for Livewire serialization (no Carbon, Enum, or objects).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function makeDataSerializationSafe(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $out[$k] = $this->makeDataSerializationSafe($v);
            } elseif ($v instanceof \Illuminate\Support\Carbon) {
                $out[$k] = $v->format('Y-m-d');
            } elseif ($v instanceof \BackedEnum) {
                $out[$k] = $v->value;
            } elseif (is_object($v)) {
                $out[$k] = null;
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * Load step 2 data. Prefill supplier_groups when exactly 1 supplier quote is selected (small payload, usually no 502).
     * For 2+ quotes we keep groups empty and build on submit.
     */
    public function loadWizardStep2Data(): void
    {
        $actions = $this->mountedActions ?? [];
        if ($actions === []) {
            return;
        }
        $idx = array_key_last($actions);
        $data = $this->mountedActions[$idx]['data'] ?? [];
        if (($data['step'] ?? 1) !== 2) {
            return;
        }
        if (! empty($data['supplier_groups'] ?? [])) {
            return;
        }
        $ids = array_map('intval', (array) ($data['supplier_quote_ids'] ?? []));
        if ($ids === []) {
            return;
        }

        /** @var Request $request */
        $request = $this->getOwnerRecord();
        $step2Data = $this->buildSupplierGroupsFormData($request, $ids);
        if (count($ids) !== 1) {
            $step2Data['supplier_groups'] = [];
        } else {
            $step2Data['supplier_groups'] = $this->supplierGroupsWithUuidKeys($step2Data['supplier_groups'] ?? []);
        }
        $step2Data = $this->makeDataSerializationSafe($step2Data);
        $this->mountedActions[$idx]['data'] = array_merge($data, $step2Data);
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
            Hidden::make('_create_with_supplier_groups')->dehydrated(false),
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

            Section::make()
                ->heading('')
                ->visible(fn (Get $get): bool => (bool) ($get('_create_with_supplier_groups') ?? false))
                ->schema([
                    SafeLabelRepeater::make('supplier_groups')
                        ->label('')
                        ->hiddenLabel()
                        ->schema([
                            Hidden::make('supplier_quote_id'),
                            Placeholder::make('supplier_name')
                                ->label('Supplier')
                                ->content(fn ($state): string => $state ?? '—'),
                            SafeLabelRepeater::make('payment_terms')
                                ->label('Payment terms')
                                ->itemLabel(function (array $state): ?string {
                                    if (! isset($state['due_days'], $state['percentage'])) {
                                        return null;
                                    }
                                    $label = "{$state['due_days']} days - {$state['percentage']}%";
                                    if ($this->getOwnerRecord()->isServiceRequest() && isset($state['job_progress']) && $state['job_progress'] !== '' && $state['job_progress'] !== null) {
                                        $label .= " - {$state['job_progress']}%";
                                    }
                                    return $label;
                                })
                                ->schema([
                                    TextInput::make('due_days')->label('Due days')->numeric()->suffix('days'),
                                    TextInput::make('percentage')->label('Percentage')->numeric()->suffix('%'),
                                    TextInput::make('job_progress')->label('Job progress %')->numeric()->nullable()->visible(fn (): bool => $this->getOwnerRecord()->isServiceRequest()),
                                ])
                                ->collapsible()
                                ->columns(3),
                            SafeLabelRepeater::make('items')
                                ->label('Line items')
                                ->schema([
                                    Hidden::make('request_item_id'),
                                    Hidden::make('article_id'),
                                    Hidden::make('supplier_quote_item_id'),
                                    Hidden::make('unit'),
                                    Hidden::make('unit_price_exc_tax'),
                                    Hidden::make('line_subtotal'),
                                    Hidden::make('line_tax'),
                                    Hidden::make('margin_amount'),
                                    Hidden::make('margin_percent'),
                                    Hidden::make('sort_order'),
                                    Grid::make(12)
                                        ->schema([
                                            TextInput::make('description')->required()->columnSpan(4),
                                            TextInput::make('quantity')->numeric()->required()->columnSpan(2)->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                            Select::make('unit_of_measure_id')
                                                ->label('Unit of measure')
                                                ->options(fn (): array => UnitOfMeasure::query()->where('team_id', $this->getOwnerRecord()->team_id)->where('is_active', true)->orderBy('label')->get()->mapWithKeys(fn (UnitOfMeasure $u): array => [$u->getKey() => $u->label])->all())
                                                ->default(fn (): ?int => UnitOfMeasure::query()->where('team_id', $this->getOwnerRecord()->team_id)->where('code', 'pcs')->where('is_active', true)->value('id'))
                                                ->selectablePlaceholder(false)
                                                ->columnSpan(3)
                                                ->live()
                                                ->afterStateUpdated(function (Set $set, ?int $state): void {
                                                    if ($state !== null) {
                                                        $uom = UnitOfMeasure::find($state);
                                                        if ($uom !== null) {
                                                            $set('unit', $uom->code ?? 'pcs');
                                                        }
                                                    }
                                                }),
                                            TextInput::make('cost_price')->label('Cost')->numeric()->columnSpan(3)->live(onBlur: true)->afterStateUpdated(function (Set $set, Get $get): void {
                                                $costPrice = (float) ($get('cost_price') ?? 0);
                                                $unitPrice = (float) ($get('unit_price') ?? 0);
                                                if ($costPrice > 0 && $unitPrice > 0) {
                                                    $marginPercent = (($unitPrice - $costPrice) / $unitPrice) * 100;
                                                    $set('margin_percent_input', (int) round($marginPercent));
                                                }
                                                $this->calculateItemTotals($set, $get);
                                            }),
                                        ]),
                                    Grid::make(12)
                                        ->schema([
                                            TextInput::make('unit_price')->label('Selling (net)')->numeric()->columnSpan(3)->live(onBlur: true)->afterStateUpdated(function (Set $set, Get $get): void {
                                                $unitPrice = (float) ($get('unit_price') ?? 0);
                                                $set('unit_price_exc_tax', round($unitPrice, 0));
                                                $costPrice = (float) ($get('cost_price') ?? 0);
                                                if ($costPrice > 0 && $unitPrice > 0) {
                                                    $marginPercent = (($unitPrice - $costPrice) / $unitPrice) * 100;
                                                    $set('margin_percent_input', (int) round($marginPercent));
                                                }
                                                $this->calculateItemTotals($set, $get);
                                            }),
                                            Select::make('tax_code_id')
                                                ->label('Tax Code')
                                                ->options(fn (): array => TaxCode::query()->where('team_id', $this->getOwnerRecord()->team_id)->where('is_active', true)->orderBy('sort_order')->get()->mapWithKeys(fn (TaxCode $t): array => [$t->getKey() => $t->display_name])->all())
                                                ->default(fn (): ?int => TaxCode::query()->where('team_id', $this->getOwnerRecord()->team_id)->where('is_default', true)->where('is_active', true)->value('id'))
                                                ->selectablePlaceholder(false)
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
                                            Hidden::make('tax_rate')->default(0)->dehydrated(),
                                            Checkbox::make('is_tax_inclusive')->label('+ Tax')->inline(false)->columnSpan(1)->live()->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                            TextInput::make('margin_percent_input')
                                                ->label('Margin %')
                                                ->numeric()->suffix('%')->columnSpan(2)
                                                ->default(function (Get $get): float {
                                                    $stored = $get('margin_percent');
                                                    if ($stored !== null && $stored !== '' && (float) $stored > 0) {
                                                        return (float) $stored;
                                                    }
                                                    return (float) ceil(Filament::getTenant()?->getErpSettings()->default_margin_percent ?? 3.0);
                                                })
                                                ->live(onBlur: true)
                                                ->afterStateHydrated(function (Set $set, Get $get): void {
                                                    $current = $get('margin_percent_input');
                                                    if ($current !== null && $current !== '' && (float) $current > 0) {
                                                        return;
                                                    }
                                                    $stored = $get('margin_percent');
                                                    $default = (int) ceil(Filament::getTenant()?->getErpSettings()->default_margin_percent ?? 3.0);
                                                    $set('margin_percent_input', $stored !== null && $stored !== '' && (float) $stored > 0 ? (int) round((float) $stored) : $default);
                                                })
                                                ->afterStateUpdated(function (Set $set, Get $get, ?float $state): void {
                                                    $marginPercent = $state ?? 0;
                                                    $costPrice = (float) ($get('cost_price') ?? 0);
                                                    if ($costPrice > 0 && $marginPercent >= 0) {
                                                        $unitPriceExcTax = round($costPrice * (1 + $marginPercent / 100), 0);
                                                        $set('unit_price', $unitPriceExcTax);
                                                        $set('unit_price_exc_tax', $unitPriceExcTax);
                                                    }
                                                    $this->calculateItemTotals($set, $get);
                                                })
                                                ->dehydrated(false),
                                            TextInput::make('line_total')->label('Line Total')->numeric()->disabled()->dehydrated()->live()->columnSpan(3),
                                        ]),
                                    SafeLabelRepeater::make('child_items')
                                        ->label('Detail items')
                                        ->schema([
                                            Hidden::make('request_item_id'),
                                            Hidden::make('supplier_quote_item_id'),
                                            TextInput::make('description')->required(),
                                            TextInput::make('quantity')->numeric()->required(),
                                            TextInput::make('cost_price')->numeric(),
                                            TextInput::make('unit_price')->numeric(),
                                            TextInput::make('line_total')->numeric(),
                                            Hidden::make('unit_of_measure_id'),
                                            Hidden::make('unit_price_exc_tax'),
                                            Hidden::make('tax_code_id'),
                                            Hidden::make('tax_rate'),
                                            Hidden::make('is_tax_inclusive'),
                                            Hidden::make('line_subtotal'),
                                            Hidden::make('line_tax'),
                                            Hidden::make('hide_from_pdf'),
                                        ])
                                        ->columns(2)
                                        ->collapsible()
                                        ->visible(fn (): bool => $this->getOwnerRecord()->isServiceRequest()),
                                ])
                                ->columns(1)
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['description'] ?? null),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['supplier_name'] ?? null)
                        ->collapsible()
                        ->defaultItems(0),
                ]),

            Section::make('Payment Terms')
                ->visible(fn (Get $get): bool => ! (bool) ($get('_create_with_supplier_groups') ?? false))
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
                                            ->visible(fn (): bool => $this->getOwnerRecord()->isServiceRequest())
                                            ->live(),
                                    ]),
                            ])
                            ->defaultItems(1)
                            ->itemLabel(function (array $state): ?string {
                                if (! isset($state['due_days'], $state['percentage'])) {
                                    return null;
                                }
                                $label = "{$state['due_days']} days - {$state['percentage']}%";
                                if ($this->getOwnerRecord()->isServiceRequest() && isset($state['job_progress']) && $state['job_progress'] !== '' && $state['job_progress'] !== null) {
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
                ->visible(fn (Get $get): bool => ! (bool) ($get('_create_with_supplier_groups') ?? false))
                ->schema([
                    Repeater::make('items')
                        ->relationship(
                            'items',
                            function ($query) use ($request) {
                                // Only show main items (exclude child items) in the main items list
                                // Child items will be nested within their parent items' Detail Items section
                                $query->whereHas('requestItem', function ($q) {
                                    $q->whereNull('parent_id');
                                })->orWhereNull('request_item_id'); // Include items without request_item_id
                            }
                        )
                        ->mutateRelationshipDataBeforeFillUsing(function (array $data, $record) use ($request): array {
                            // Prefill margin_percent_input so the Margin % field is never empty; round to int so all items show same default (e.g. 3)
                            $stored = $data['margin_percent'] ?? ($record->margin_percent ?? null);
                            $default = (int) ceil(Filament::getTenant()?->getErpSettings()->default_margin_percent ?? 3.0);
                            $data['margin_percent_input'] = ($stored !== null && $stored !== '' && (float) $stored > 0)
                                ? (int) round((float) $stored)
                                : $default;

                            // Add child_items to each MAIN item when loading from relationship
                            // $record here is the BuyerQuoteItem, we need to check if it's a main item
                            if ($request->isServiceRequest() && isset($data['request_item_id']) && $data['request_item_id'] !== null) {
                                // Check if this is a main item (not a child item)
                                $requestItem = $request->items()->find($data['request_item_id']);
                                if ($requestItem !== null && $requestItem->parent_id === null && $requestItem->children()->exists()) {
                                    // Get the BuyerQuote from the BuyerQuoteItem's relationship
                                    $buyerQuote = null;
                                    if ($record instanceof \App\Models\BuyerQuoteItem) {
                                        // Load the relationship if not already loaded
                                        if (!$record->relationLoaded('buyerQuote')) {
                                            $record->load('buyerQuote');
                                        }
                                        $buyerQuote = $record->buyerQuote;
                                    } elseif (isset($data['buyer_quote_id'])) {
                                        $buyerQuote = \App\Models\BuyerQuote::find($data['buyer_quote_id']);
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
                                                ? ($data['tax_rate'] ?? (string) $record->tax_rate ?? '0.0000')
                                                : (string) ($defaultTaxCode?->rate ?? 0);
                                            $mainItemIsTaxInclusive = ($data['tax_code_id'] ?? $record->tax_code_id) !== null
                                                ? ($data['is_tax_inclusive'] ?? $record->is_tax_inclusive ?? false)
                                                : ($defaultTaxCode?->is_inclusive_default ?? false);
                                            
                                            $data['child_items'] = $childBuyerQuoteItems->map(function ($childItem) use ($mainItemTaxCodeId, $mainItemTaxRate, $mainItemIsTaxInclusive) {
                                                // Use stored tax information, or fallback to main item's tax
                                                $taxCodeId = $childItem->tax_code_id ?? $mainItemTaxCodeId;
                                                $taxRate = $childItem->tax_code_id !== null 
                                                    ? (string) $childItem->tax_rate 
                                                    : $mainItemTaxRate;
                                                $isTaxInclusive = $childItem->tax_code_id !== null
                                                    ? $childItem->is_tax_inclusive
                                                    : $mainItemIsTaxInclusive;
                                                
                                                return [
                                                    'request_item_id' => $childItem->request_item_id,
                                                    'supplier_quote_item_id' => $childItem->supplier_quote_item_id,
                                                    'description' => $childItem->description,
                                                    'quantity' => (string) $childItem->quantity,
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
                                        ->afterStateHydrated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get))
                                        ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                    TextInput::make('tax_rate')
                                        ->label('Tax %')
                                        ->numeric()
                                        ->default(0)
                                        ->step(0.01)
                                        ->columnSpan(1)
                                        ->disabled()
                                        ->dehydrated(),
                                    // Margin %: default from general settings. Unit price = cost × (1 + margin%/100); margin% = (selling - cost)/cost×100. +Tax adds tax to line total.
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
                                            $defaultMargin = (float) ceil(Filament::getTenant()?->getErpSettings()->default_margin_percent ?? 3.0);
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
                                                $unitPriceExcTax = round($costPrice * (1 + $marginPercent / 100), 0);
                                                $set('unit_price', $unitPriceExcTax);
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
                                        ->live()
                                        ->columnSpan(2),
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

                                                        ->columnSpan(1)
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
                                                        ->step(0.01)
                                                        ->columnSpan(1)
                                                        ->disabled()
                                                        ->dehydrated(),
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
                                        ->visible(fn (Get $get): bool => $request->isServiceRequest()),
                                ])
                                ->visible(fn (Get $get): bool => $request->isServiceRequest())
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
     * Form schema for the "New buyer quote" wizard: step 1 = select supplier quotes, step 2 = create form.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function getNewBuyerQuoteWizardFormSchema(): array
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return [
            Hidden::make('step')->default(1)->dehydrated(false),
            Section::make('Select supplier quotes')
                ->visible(fn (Get $get): bool => (int) ($get('step') ?? 1) === 1)
                ->schema([
                    Select::make('supplier_quote_ids')
                        ->label('Supplier quotes')
                        ->options(
                            SupplierQuote::query()
                                ->where('request_id', $request->getKey())
                                ->where('status', SupplierQuoteStatus::SELECTED)
                                ->with('supplier')
                                ->get()
                                ->mapWithKeys(fn (SupplierQuote $sq): array => [
                                    $sq->getKey() => $sq->quote_number . ' — ' . ($sq->supplier?->name ?? ''),
                                ])
                        )
                        ->multiple()
                        ->required()
                        ->searchable()
                        ->preload(),
                    Checkbox::make('create_separate')
                        ->label('Create separate buyer quote for each supplier')
                        ->helperText('When selected, one buyer quote will be created per selected supplier quote.')
                        ->visible(fn (Get $get): bool => count($get('supplier_quote_ids') ?? []) > 1),
                ]),
            Section::make('Quote Details')
                ->visible(fn (Get $get): bool => (int) ($get('step') ?? 1) === 2)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('status')
                                ->options(BuyerQuoteStatus::class)
                                ->default(BuyerQuoteStatus::DRAFT)
                                ->required()
                                ->selectablePlaceholder(false),
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
                                    $team = Filament::getTenant();
                                    $defaultCode = $team?->getErpSettings()->default_currency ?? 'USD';
                                    return Currency::query()->where('code', $defaultCode)->where('is_active', true)->value('id');
                                })
                                ->required()
                                ->selectablePlaceholder(false),
                            DatePicker::make('valid_until')
                                ->label('Valid Until')
                                ->default(function (): \Illuminate\Support\Carbon {
                                    $team = Filament::getTenant();
                                    $validityDays = $team?->getErpSettings()->quote_validity_days ?? 30;
                                    return now()->addDays($validityDays);
                                }),
                            Hidden::make('exchange_rate')->default(1),
                        ]),
                ]),
            Hidden::make('_create_with_supplier_groups')->default(true)->dehydrated(false),
            Section::make()
                ->heading('')
                ->key(fn (Get $get): string => 'wizard-by-supplier-step-' . ((int) ($get('step') ?? 1)))
                ->visible(fn (Get $get): bool => (int) ($get('step') ?? 1) === 2)
                ->schema([
                    ViewField::make('_load_step2')
                        ->label('')
                        ->view('filament.forms.components.buyer-quote-wizard-load-step2')
                        ->dehydrated(false),
                    ViewField::make('_wizard_build_hint')
                        ->label('')
                        ->view('filament.forms.components.buyer-quote-wizard-build-hint')
                        ->visible(fn (Get $get): bool => empty($get('supplier_groups')) && ! empty($get('supplier_quote_ids')))
                        ->dehydrated(false),
                    SafeLabelRepeater::make('supplier_groups')
                        ->label('')
                        ->hiddenLabel()
                        ->addable(false)
                        ->schema([
                            Hidden::make('supplier_quote_id'),
                            Hidden::make('supplier_name'),
                            Section::make('Payment Terms')
                                ->schema([
                                    Grid::make(3)
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
                                    SafeLabelRepeater::make('payment_terms')
                                        ->label('Payment terms')
                                        ->itemLabel(function (array $state) use ($request): ?string {
                                            if (! isset($state['due_days'], $state['percentage'])) {
                                                return null;
                                            }
                                            $label = "{$state['due_days']} days - {$state['percentage']}%";
                                            if ($request->isServiceRequest() && isset($state['job_progress']) && $state['job_progress'] !== '' && $state['job_progress'] !== null) {
                                                $label .= " - {$state['job_progress']}%";
                                            }
                                            return $label;
                                        })
                                        ->schema([
                                            TextInput::make('due_days')->label('Due days')->numeric()->suffix('days'),
                                            TextInput::make('percentage')->label('Percentage')->numeric()->suffix('%'),
                                            TextInput::make('job_progress')->label('Job progress %')->numeric()->nullable()->visible(fn (): bool => $request->isServiceRequest()),
                                        ])
                                        ->columns(3),
                                ]),
                            SafeLabelRepeater::make('items')
                                ->label('Line items')
                                ->schema([
                                            Hidden::make('request_item_id'),
                                            Hidden::make('article_id'),
                                            Hidden::make('supplier_quote_item_id'),
                                            Hidden::make('unit'),
                                            Hidden::make('unit_price_exc_tax'),
                                            Hidden::make('line_subtotal'),
                                            Hidden::make('line_tax'),
                                            Hidden::make('margin_amount'),
                                            Hidden::make('margin_percent'),
                                            Hidden::make('sort_order'),
                                            Grid::make(12)
                                                ->schema([
                                                    TextInput::make('description')->required()->columnSpan(4),
                                                    TextInput::make('quantity')
                                                        ->numeric()->required()->columnSpan(2)->live(onBlur: true)
                                                        ->afterStateHydrated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get))
                                                        ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                                    Select::make('unit_of_measure_id')
                                                        ->label('Unit of measure')
                                                        ->options(fn (): array => UnitOfMeasure::query()->where('team_id', $request->team_id)->where('is_active', true)->orderBy('label')->get()->mapWithKeys(fn (UnitOfMeasure $u): array => [$u->getKey() => $u->label])->all())
                                                        ->default(fn (): ?int => UnitOfMeasure::query()->where('team_id', $request->team_id)->where('code', 'pcs')->where('is_active', true)->value('id'))
                                                        ->selectablePlaceholder(false)
                                                        ->columnSpan(3)
                                                        ->live()
                                                        ->afterStateUpdated(function (Set $set, ?int $state): void {
                                                            if ($state !== null) {
                                                                $uom = UnitOfMeasure::find($state);
                                                                if ($uom !== null) {
                                                                    $set('unit', $uom->code ?? 'pcs');
                                                                }
                                                            }
                                                        }),
                                                    TextInput::make('cost_price')->label('Cost')->numeric()->columnSpan(3)->live(onBlur: true)->afterStateUpdated(function (Set $set, Get $get): void {
                                                        $costPrice = (float) ($get('cost_price') ?? 0);
                                                        $unitPrice = (float) ($get('unit_price') ?? 0);
                                                        if ($costPrice > 0 && $unitPrice > 0) {
                                                            $marginPercent = (($unitPrice - $costPrice) / $costPrice) * 100;
                                                            $set('margin_percent_input', (int) round($marginPercent));
                                                        }
                                                        $this->calculateItemTotals($set, $get);
                                                    }),
                                                ]),
                                            Grid::make(12)
                                                ->schema([
                                                    TextInput::make('unit_price')
                                                        ->label('Selling (net)')->numeric()->columnSpan(3)->live(onBlur: true)
                                                        ->afterStateHydrated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get))
                                                        ->afterStateUpdated(function (Set $set, Get $get): void {
                                                            $unitPrice = (float) ($get('unit_price') ?? 0);
                                                            $set('unit_price_exc_tax', round($unitPrice, 0));
                                                            $costPrice = (float) ($get('cost_price') ?? 0);
                                                            if ($costPrice > 0 && $unitPrice > 0) {
                                                                $marginPercent = (($unitPrice - $costPrice) / $costPrice) * 100;
                                                                $set('margin_percent_input', (int) round($marginPercent));
                                                            }
                                                            $this->calculateItemTotals($set, $get);
                                                        }),
                                                    Select::make('tax_code_id')
                                                        ->label('Tax Code')
                                                        ->options(fn (): array => TaxCode::query()->where('team_id', $request->team_id)->where('is_active', true)->orderBy('sort_order')->get()->mapWithKeys(fn (TaxCode $t): array => [$t->getKey() => $t->display_name])->all())
                                                        ->default(fn (): ?int => TaxCode::query()->where('team_id', $request->team_id)->where('is_default', true)->where('is_active', true)->value('id'))
                                                        ->selectablePlaceholder(false)
                                                        ->columnSpan(3)
                                                        ->live()
                                                        ->afterStateUpdated(function (Set $set, Get $get, ?int $state) use ($request): void {
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
                                                    Hidden::make('tax_rate')->default(0)->dehydrated(),
                                                    Checkbox::make('is_tax_inclusive')
                                                        ->label('+ Tax')->inline(false)->columnSpan(1)->live()
                                                        ->afterStateHydrated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get))
                                                        ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                                    TextInput::make('margin_percent_input')
                                                        ->label('Margin %')
                                                        ->numeric()->suffix('%')->columnSpan(2)
                                                        ->default(function (): float {
                                                            /** @var \App\Models\Team|null $team */
                                                            $team = Filament::getTenant();
                
                                                            return ceil($team?->getErpSettings()->default_margin_percent ?? 3.0);
                                                        })
                                                        ->live(onBlur: true)
                                                        ->afterStateHydrated(function (Set $set, Get $get): void {
                                                            $current = $get('margin_percent_input');
                                                            if ($current !== null && $current !== '' && (float) $current > 0) {
                                                                return;
                                                            }
                                                            $stored = $get('margin_percent');
                                                            $default = (int) ceil(Filament::getTenant()?->getErpSettings()->default_margin_percent ?? 3.0);
                                                            $set('margin_percent_input', $stored !== null && $stored !== '' && (float) $stored > 0 ? (int) round((float) $stored) : $default);
                                                        })
                                                        ->afterStateUpdated(function (Set $set, Get $get, ?float $state): void {
                                                            $marginPercent = $state ?? 0;
                                                            $costPrice = (float) ($get('cost_price') ?? 0);
                                                            if ($costPrice > 0 && $marginPercent >= 0) {
                                                                $unitPriceExcTax = round($costPrice * (1 + $marginPercent / 100), 0);
                                                                $set('unit_price', $unitPriceExcTax);
                                                                $set('unit_price_exc_tax', $unitPriceExcTax);
                                                            }
                                                            $this->calculateItemTotals($set, $get);
                                                        })
                                                        ->dehydrated(false),
                                                    TextInput::make('line_total')
                                                        ->label('Line Total')->numeric()->disabled()->dehydrated()->live()->columnSpan(3),
                                                ]),
                                            SafeLabelRepeater::make('child_items')
                                                ->label('Detail items')
                                                ->schema([
                                                    Hidden::make('request_item_id'),
                                                    Hidden::make('supplier_quote_item_id'),
                                                    TextInput::make('description')->required(),
                                                    TextInput::make('quantity')->numeric()->required(),
                                                    TextInput::make('cost_price')->numeric(),
                                                    TextInput::make('unit_price')->numeric(),
                                                    TextInput::make('line_total')->numeric(),
                                                    Hidden::make('unit_of_measure_id'),
                                                    Hidden::make('unit_price_exc_tax'),
                                                    Hidden::make('tax_code_id'),
                                                    Hidden::make('tax_rate'),
                                                    Hidden::make('is_tax_inclusive'),
                                                    Hidden::make('line_subtotal'),
                                                    Hidden::make('line_tax'),
                                                    Hidden::make('hide_from_pdf'),
                                                ])
                                                ->columns(2)
                                                ->collapsible()
                                                ->visible(fn (): bool => $request->isServiceRequest()),
                                        ])
                                        ->columns(1)
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => $state['description'] ?? null),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['supplier_name'] ?? null)
                        ->collapsible()
                        ->defaultItems(0),
                ]),
            Section::make('Summary')
                ->visible(fn (Get $get): bool => (int) ($get('step') ?? 1) === 2)
                ->schema([
                    Grid::make(4)
                        ->schema([
                            Placeholder::make('subtotal_display')
                                ->label('Subtotal')
                                ->live()
                                ->content(function (Get $get): string {
                                    $groups = $get('supplier_groups') ?? [];
                                    $subtotal = 0.0;
                                    foreach ($groups as $group) {
                                        foreach ($group['items'] ?? [] as $item) {
                                            $subtotal += (float) ($item['line_subtotal'] ?? 0);
                                            foreach ($item['child_items'] ?? [] as $child) {
                                                $subtotal += (float) ($child['line_subtotal'] ?? 0);
                                            }
                                        }
                                    }
                                    $currencyId = $get('currency_id');
                                    $currency = $currencyId !== null ? Currency::find($currencyId) : null;
                                    return $currency instanceof Currency ? $currency->formatNumber($subtotal) : number_format($subtotal, 2);
                                }),
                            Placeholder::make('tax_total_display')
                                ->label('Tax Total')
                                ->live()
                                ->content(function (Get $get): string {
                                    $groups = $get('supplier_groups') ?? [];
                                    $taxTotal = 0.0;
                                    foreach ($groups as $group) {
                                        foreach ($group['items'] ?? [] as $item) {
                                            $taxTotal += (float) ($item['line_tax'] ?? 0);
                                            foreach ($item['child_items'] ?? [] as $child) {
                                                $taxTotal += (float) ($child['line_tax'] ?? 0);
                                            }
                                        }
                                    }
                                    $currencyId = $get('currency_id');
                                    $currency = $currencyId !== null ? Currency::find($currencyId) : null;
                                    return $currency instanceof Currency ? $currency->formatNumber($taxTotal) : number_format($taxTotal, 2);
                                }),
                            Placeholder::make('total_display')
                                ->label('Total')
                                ->live()
                                ->content(function (Get $get): string {
                                    $groups = $get('supplier_groups') ?? [];
                                    $total = 0.0;
                                    foreach ($groups as $group) {
                                        foreach ($group['items'] ?? [] as $item) {
                                            $total += (float) ($item['line_total'] ?? 0);
                                            foreach ($item['child_items'] ?? [] as $child) {
                                                $total += (float) ($child['line_total'] ?? 0);
                                            }
                                        }
                                    }
                                    $currencyId = $get('currency_id');
                                    $currency = $currencyId !== null ? Currency::find($currencyId) : null;
                                    return $currency instanceof Currency ? $currency->format($total) : number_format($total, 2);
                                }),
                            Placeholder::make('margin_display')
                                ->label('Total Margin')
                                ->live()
                                ->content(function (Get $get): string {
                                    $groups = $get('supplier_groups') ?? [];
                                    $totalMargin = 0.0;
                                    $totalSelling = 0.0;
                                    foreach ($groups as $group) {
                                        foreach ($group['items'] ?? [] as $item) {
                                            $qty = (float) ($item['quantity'] ?? 0);
                                            $totalMargin += (float) ($item['margin_amount'] ?? 0) * $qty;
                                            $totalSelling += (float) ($item['unit_price_exc_tax'] ?? $item['unit_price'] ?? 0) * $qty;
                                        }
                                    }
                                    $marginPercent = $totalSelling > 0 ? ($totalMargin / $totalSelling) * 100 : 0;
                                    $currencyId = $get('currency_id');
                                    $currency = $currencyId !== null ? Currency::find($currencyId) : null;
                                    $formatted = $currency instanceof Currency ? $currency->formatNumber($totalMargin) : number_format($totalMargin, 2);
                                    return sprintf('%s (%.1f%%)', $formatted, $marginPercent);
                                }),
                        ]),
                ])
                ->collapsible(),
            Section::make('Notes')
                ->visible(fn (Get $get): bool => (int) ($get('step') ?? 1) === 2)
                ->schema([
                    Textarea::make('notes')->label('Notes (Visible to Buyer)')->rows(2),
                    Textarea::make('internal_notes')->label('Internal Notes')->rows(2)->helperText('Not visible on buyer PDF'),
                    Textarea::make('terms_and_conditions')->label('Terms & Conditions')->rows(3),
                ])
                ->collapsed(),
        ];
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
                Action::make('newBuyerQuoteWizard')
                    ->label('New buyer quote')
                    ->icon('heroicon-o-plus')
                    ->size(Size::Medium)
                    ->modalHeading('New buyer quote')
                    ->modalDescription(function (): string {
                        $step = 1;
                        $actions = $this->mountedActions ?? [];
                        if ($actions !== [] && ($idx = array_key_last($actions)) !== null && isset($actions[$idx]['data']['step'])) {
                            $step = (int) $actions[$idx]['data']['step'];
                        }
                        return $step === 1
                            ? 'Choose one or more supplier quotes. You can create one combined quote or separate quotes per supplier.'
                            : 'Review and edit the quote, then create.';
                    })
                    ->form(fn (): array => $this->getNewBuyerQuoteWizardFormSchema())
                    ->modalSubmitActionLabel(function (): string {
                        $step = 1;
                        $actions = $this->mountedActions ?? [];
                        if ($actions !== [] && ($idx = array_key_last($actions)) !== null && isset($actions[$idx]['data']['step'])) {
                            $step = (int) $actions[$idx]['data']['step'];
                        }
                        return $step === 1 ? 'Continue' : 'Create buyer quote';
                    })
                    ->action(function (array $data) use ($request): void {
                        $step = (int) ($data['step'] ?? 1);
                        $ids = array_map('intval', $data['supplier_quote_ids'] ?? []);

                        if ($step === 1) {
                            if (empty($ids)) {
                                Notification::make()
                                    ->warning()
                                    ->title('Select at least one supplier quote')
                                    ->send();
                                return;
                            }
                            if (! empty($data['create_separate']) && count($ids) > 1) {
                                $created = 0;
                                foreach ($ids as $supplierQuoteId) {
                                    $this->createBuyerQuoteFromSupplierQuote($request, $supplierQuoteId);
                                    $created++;
                                }
                                Notification::make()
                                    ->success()
                                    ->title('Created ' . $created . ' buyer quote(s)')
                                    ->send();
                                $this->unmountAction();
                                return;
                            }
                            // Advance to step 2 in same modal: merge minimal state so form re-renders as create form; full data loads via loadWizardStep2Data().
                            $idx = array_key_last($this->mountedActions ?? []);
                            if ($idx !== null && isset($this->mountedActions[$idx])) {
                                $minimal = $this->buildSupplierGroupsFormData($request, $ids);
                                $minimal['step'] = 2;
                                $minimal['supplier_quote_ids'] = $ids;
                                $minimal['supplier_groups'] = [];
                                $minimal = $this->makeDataSerializationSafe($minimal);
                                $this->mountedActions[$idx]['data'] = array_merge(
                                    $this->mountedActions[$idx]['data'] ?? [],
                                    $minimal
                                );
                            }
                            throw new Halt();
                        }

                        // Step 2: create the buyer quote (build supplier_groups on submit if empty to avoid 502 from large Livewire payload)
                        $record = new BuyerQuote();
                        $record->request_id = $request->getKey();
                        $record->buyer_id = $request->buyer_id;
                        $record->status = $data['status'] ?? BuyerQuoteStatus::DRAFT;
                        $record->currency_id = $data['currency_id'];
                        $record->exchange_rate = (string) ($data['exchange_rate'] ?? 1);
                        $record->valid_until = $data['valid_until'] ?? now()->addDays(30);
                        $dataToSave = $data;
                        if (empty($dataToSave['supplier_groups']) && ! empty($ids)) {
                            $built = $this->buildSupplierGroupsFormData($request, $ids);
                            $dataToSave['supplier_groups'] = $this->supplierGroupsWithUuidKeys($built['supplier_groups'] ?? []);
                            $dataToSave['_create_with_supplier_groups'] = true;
                        }
                        $firstGroup = is_array($dataToSave['supplier_groups'] ?? null) && $dataToSave['supplier_groups'] !== []
                            ? reset($dataToSave['supplier_groups'])
                            : null;
                        $record->prepayment_type = is_array($firstGroup) && isset($firstGroup['prepayment_type'])
                            ? $firstGroup['prepayment_type']
                            : ($data['prepayment_type'] ?? PrepaymentType::PERCENT->value);
                        $record->prepayment_amount = is_array($firstGroup) && isset($firstGroup['prepayment_amount'])
                            ? (string) $firstGroup['prepayment_amount']
                            : (string) ($data['prepayment_amount'] ?? 0);
                        $record->subtotal = '0';
                        $record->tax_total = '0';
                        $record->total = '0';
                        $record->notes = $data['notes'] ?? null;
                        $record->internal_notes = $data['internal_notes'] ?? null;
                        $record->terms_and_conditions = $data['terms_and_conditions'] ?? null;
                        $record->save();
                        $this->saveBuyerQuoteFromSupplierGroups($record, $dataToSave, $request);
                        Notification::make()
                            ->success()
                            ->title('Buyer quote created')
                            ->send();
                        $this->unmountAction();
                    }),
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small)
                    ->modalWidth('7xl')
                    ->visible(false)
                    ->fillForm(function (\Filament\Actions\CreateAction $action) use ($request): array {
                        $supplierQuoteIds = array_map('intval', $action->getArguments()['supplier_quote_ids'] ?? []);
                        $data = $this->buildSupplierGroupsFormData($request, $supplierQuoteIds);
                        $data['supplier_groups'] = $this->supplierGroupsWithUuidKeys($data['supplier_groups'] ?? []);
                        return $this->makeDataSerializationSafe($data);
                    })
                    ->mutateFormDataUsing(function (array $data) use ($request): array {
                        $data['request_id'] = $request->getKey();
                        $data['buyer_id'] = $request->buyer_id;

                        // Store child_items data keyed by request_item_id for reliable matching
                        // Child items will be saved separately in the after() callback
                        if (isset($data['items']) && is_array($data['items'])) {
                            foreach ($data['items'] as $index => $item) {
                                if (isset($item['child_items']) && is_array($item['child_items']) && !empty($item['child_items'])) {
                                    // Store child_items keyed by request_item_id for reliable matching
                                    $requestItemId = $item['request_item_id'] ?? null;
                                    if ($requestItemId !== null) {
                                        $data['_child_items'][$requestItemId] = $item['child_items'];
                                    }
                                }
                                // Always remove child_items from the item data so Filament doesn't try to save it
                                unset($data['items'][$index]['child_items']);
                            }
                        }

                        return $data;
                    })
                    ->after(function (BuyerQuote $record, array $data) use ($request): void {
                        // New flow: create from supplier_groups (payment terms and items per supplier)
                        if (! empty($data['supplier_groups'] ?? [])) {
                            $this->saveBuyerQuoteFromSupplierGroups($record, $data, $request);
                            return;
                        }

                        // Refresh media relationship to ensure it's loaded
                        $record->load('media');

                        // Get all main items that were just created, keyed by request_item_id
                        $mainItems = $record->items()
                            ->whereNotNull('request_item_id')
                            ->orderBy('sort_order')
                            ->get()
                            ->keyBy('request_item_id');

                        // Process child items from form data
                        // Child items are stored in _child_items array keyed by request_item_id
                        $processedRequestItemIds = [];
                        
                        // Get default tax code as fallback
                        $defaultTaxCode = TaxCode::query()
                            ->where('team_id', $request->team_id)
                            ->where('is_default', true)
                            ->where('is_active', true)
                            ->first();
                        
                        if (isset($data['_child_items']) && is_array($data['_child_items'])) {
                            foreach ($data['_child_items'] as $requestItemId => $childItemsData) {
                                // Match by request_item_id instead of array index for reliability
                                $mainItem = $mainItems->get($requestItemId);
                                
                                if ($mainItem !== null && is_array($childItemsData) && !empty($childItemsData)) {
                                    // Save child items as separate BuyerQuoteItem records
                                    foreach ($childItemsData as $childItemData) {
                                        // Use tax information from form data, or inherit from main item, or fallback to default tax code
                                        $taxCodeId = $childItemData['tax_code_id'] ?? $mainItem->tax_code_id ?? $defaultTaxCode?->getKey();
                                        $taxRate = isset($childItemData['tax_code_id']) && $childItemData['tax_code_id'] !== null
                                            ? ($childItemData['tax_rate'] ?? '0.0000')
                                            : ($mainItem->tax_code_id !== null 
                                                ? (string) $mainItem->tax_rate 
                                                : (string) ($defaultTaxCode?->rate ?? 0));
                                        $isTaxInclusive = isset($childItemData['tax_code_id']) && $childItemData['tax_code_id'] !== null
                                            ? ($childItemData['is_tax_inclusive'] ?? false)
                                            : ($mainItem->tax_code_id !== null
                                                ? $mainItem->is_tax_inclusive
                                                : ($defaultTaxCode?->is_inclusive_default ?? false));
                                        
                                        $record->items()->create([
                                            'request_item_id' => $childItemData['request_item_id'] ?? null,
                                            'supplier_quote_item_id' => $childItemData['supplier_quote_item_id'] ?? null,
                                            'description' => $childItemData['description'] ?? '',
                                            'quantity' => $childItemData['quantity'] ?? '1.0000',
                                            'unit_of_measure_id' => $childItemData['unit_of_measure_id'] ?? null,
                                            'cost_price' => $childItemData['cost_price'] ?? '0.0000',
                                            'unit_price' => $childItemData['unit_price'] ?? '0.0000',
                                            'unit_price_exc_tax' => $childItemData['unit_price_exc_tax'] ?? '0.0000',
                                            'tax_code_id' => $taxCodeId,
                                            'tax_rate' => $taxRate,
                                            'is_tax_inclusive' => $isTaxInclusive,
                                            'line_subtotal' => $childItemData['line_subtotal'] ?? '0.0000',
                                            'line_tax' => $childItemData['line_tax'] ?? '0.0000',
                                            'line_total' => $childItemData['line_total'] ?? '0.0000',
                                            'hide_from_pdf' => $childItemData['hide_from_pdf'] ?? false,
                                            'sort_order' => $mainItem->sort_order + 1, // Place after main item
                                        ]);
                                    }
                                    $processedRequestItemIds[] = $requestItemId;
                                }
                            }
                        }

                        // Fallback: If child items weren't created from form data, create them from RequestItem children
                        // This ensures child items are always created for Service requests with child RequestItems
                        if ($request->isServiceRequest()) {
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

                            $taxRate = $defaultTaxCode !== null ? (float) $defaultTaxCode->rate : 0.0;
                            $addTax = $defaultTaxCode !== null && $defaultTaxCode->is_inclusive_default;

                            foreach ($mainItems as $mainItem) {
                                // Skip if already processed from form data
                                if (in_array($mainItem->request_item_id, $processedRequestItemIds)) {
                                    continue;
                                }

                                $requestItem = $request->items()->find($mainItem->request_item_id);
                                if ($requestItem === null || !$requestItem->children()->exists()) {
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

                                    $childUnitPriceExcTax = $childCostPrice > 0 ? round($childCostPrice * (1 + $defaultMarginPercent / 100), 0) : 0.0;
                                    $childUnitPrice = round($childUnitPriceExcTax, 0);

                                    $childLineSubtotal = $childQuantity * $childUnitPriceExcTax;
                                    $childLineTax = $taxRate > 0 && $addTax ? $childLineSubtotal * $taxRate / 100 : 0;
                                    $childLineTotal = $addTax && $taxRate > 0 ? $childLineSubtotal + $childLineTax : $childLineSubtotal;

                                    $childMarginAmount = $childUnitPriceExcTax - $childCostPrice;
                                    $childMarginPercent = $childCostPrice > 0 ? ($childMarginAmount / $childCostPrice) * 100 : 0;

                                    $record->items()->create([
                                        'request_item_id' => $childRequestItem->getKey(),
                                        'supplier_quote_item_id' => $childSupplierQuoteItem?->getKey(),
                                        'description' => $childRequestItem->description,
                                        'quantity' => (string) $childRequestItem->quantity,
                                        'unit_of_measure_id' => $childRequestItem->unit_of_measure_id,
                                        'cost_price' => (string) $childCostPrice,
                                        'unit_price' => (string) $childUnitPrice,
                                        'unit_price_exc_tax' => (string) round($childUnitPriceExcTax, 0),
                                        'tax_code_id' => $defaultTaxCode?->getKey(),
                                        'tax_rate' => (string) $taxRate,
                                        'is_tax_inclusive' => $addTax,
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

                                // Unit price (Selling net) = Cost × (1 + margin%/100)
                                $unitPriceExcTax = $costPrice > 0
                                    ? round($costPrice * (1 + $defaultMarginPercent / 100), 4)
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
                    ->visible(function () use ($request): bool {
                        // Hide if buyer quotes don't exist
                        if (! $request->buyerQuotes()->exists()) {
                            return false;
                        }
                        
                        // Hide if PNL is not created
                        if (! $request->profitAndLosses()->exists()) {
                            return false;
                        }
                        
                        // Hide if PNL is not approved
                        /** @var ProfitAndLoss|null $latestPNL */
                        $latestPNL = $request->profitAndLosses()->latest()->first();
                        if ($latestPNL === null) {
                            return false;
                        }
                        
                        return $latestPNL->status->isApproved();
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
                        ->fillForm(function (BuyerQuote $record) use ($request): array {
                            // Eager load relationships to avoid N+1 queries
                            // Get all items from the relationship with requestItem relationship loaded
                            $allItems = $record->items()
                                ->with('requestItem')
                                ->orderBy('sort_order')
                                ->get();
                            
                            // Filter to only main items (exclude child items from the main list)
                            $mainItems = $allItems->filter(function ($item) {
                                return $item->requestItem === null || $item->requestItem->parent_id === null;
                            });
                            
                            // Get all child items grouped by their parent request_item_id
                            $allChildItems = $allItems
                                ->filter(function ($item) {
                                    return $item->requestItem !== null && $item->requestItem->parent_id !== null;
                                })
                                ->groupBy(function ($item) {
                                    // Group by parent request_item_id (the parent_id of the RequestItem)
                                    // Convert to string to ensure consistent key matching
                                    return (string) ($item->requestItem->parent_id ?? null);
                                });
                            
                            // Build items array with child_items populated
                            $items = [];
                            foreach ($mainItems as $mainItem) {
                                $itemData = [
                                    'id' => $mainItem->id,
                                    'request_item_id' => $mainItem->request_item_id,
                                    'article_id' => $mainItem->article_id,
                                    'supplier_quote_item_id' => $mainItem->supplier_quote_item_id,
                                    'description' => $mainItem->description,
                                    'quantity' => (string) $mainItem->quantity,
                                    'unit_of_measure_id' => $mainItem->unit_of_measure_id,
                                    'unit' => $mainItem->unit instanceof \App\Enums\Unit ? $mainItem->unit->value : (string) $mainItem->unit,
                                    'cost_price' => (string) $mainItem->cost_price,
                                    'unit_price' => (string) $mainItem->unit_price,
                                    'unit_price_exc_tax' => (string) $mainItem->unit_price_exc_tax,
                                    'tax_code_id' => $mainItem->tax_code_id,
                                    'tax_rate' => (string) $mainItem->tax_rate,
                                    'is_tax_inclusive' => $mainItem->is_tax_inclusive,
                                    'line_subtotal' => (string) $mainItem->line_subtotal,
                                    'line_tax' => (string) $mainItem->line_tax,
                                    'line_total' => (string) $mainItem->line_total,
                                    'margin_amount' => (string) $mainItem->margin_amount,
                                    'margin_percent' => (string) $mainItem->margin_percent,
                                    'hide_from_pdf' => $mainItem->hide_from_pdf ?? false,
                                    'notes' => $mainItem->notes,
                                    'sort_order' => $mainItem->sort_order,
                                ];
                                
                                // Add child_items if this is a Service request
                                if ($request->isServiceRequest() && $mainItem->request_item_id !== null) {
                                    // Use the pre-computed grouped child items
                                    // Convert to string to match the grouping key
                                    $childBuyerQuoteItems = $allChildItems->get((string) $mainItem->request_item_id);
                                    
                                    if ($childBuyerQuoteItems !== null && $childBuyerQuoteItems->isNotEmpty()) {
                                        // Get default tax code as fallback
                                        $defaultTaxCode = TaxCode::query()
                                            ->where('team_id', $request->team_id)
                                            ->where('is_default', true)
                                            ->where('is_active', true)
                                            ->first();
                                        
                                        // Use main item's tax information as primary fallback, then default tax code
                                        $mainItemTaxCodeId = $mainItem->tax_code_id ?? $defaultTaxCode?->getKey();
                                        $mainItemTaxRate = $mainItem->tax_code_id !== null 
                                            ? (string) $mainItem->tax_rate 
                                            : (string) ($defaultTaxCode?->rate ?? 0);
                                        $mainItemIsTaxInclusive = $mainItem->tax_code_id !== null
                                            ? $mainItem->is_tax_inclusive
                                            : ($defaultTaxCode?->is_inclusive_default ?? false);
                                        
                                        $itemData['child_items'] = $childBuyerQuoteItems->map(function ($childItem) use ($defaultTaxCode, $mainItemTaxCodeId, $mainItemTaxRate, $mainItemIsTaxInclusive) {
                                            // Use stored tax information, or fallback to main item's tax, then default tax code
                                            $taxCodeId = $childItem->tax_code_id ?? $mainItemTaxCodeId;
                                            $taxRate = $childItem->tax_code_id !== null 
                                                ? (string) $childItem->tax_rate 
                                                : $mainItemTaxRate;
                                            $isTaxInclusive = $childItem->tax_code_id !== null
                                                ? $childItem->is_tax_inclusive
                                                : $mainItemIsTaxInclusive;
                                            
                                            return [
                                                'request_item_id' => $childItem->request_item_id,
                                                'supplier_quote_item_id' => $childItem->supplier_quote_item_id,
                                                'description' => $childItem->description,
                                                'quantity' => (string) $childItem->quantity,
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
                                        // Initialize empty array so the repeater shows up
                                        $itemData['child_items'] = [];
                                    }
                                }
                                
                                $items[] = $itemData;
                            }
                            
                            return ['items' => $items];
                        })
                        ->mutateFormDataUsing(function (array $data): array {
                            // Ensure unit_price_exc_tax matches unit_price for all items (both should be net price)
                            if (isset($data['items']) && is_array($data['items'])) {
                                foreach ($data['items'] as $key => $item) {
                                    if (isset($item['unit_price']) && (float) $item['unit_price'] > 0) {
                                        $data['items'][$key]['unit_price_exc_tax'] = round((float) $item['unit_price'], 0);
                                    }
                                }
                            }
                            
                            // Store child_items data keyed by request_item_id for reliable matching
                            if (isset($data['items']) && is_array($data['items'])) {
                                foreach ($data['items'] as $index => $item) {
                                    if (isset($item['child_items']) && is_array($item['child_items']) && !empty($item['child_items'])) {
                                        // Store child_items keyed by request_item_id for reliable matching
                                        $requestItemId = $item['request_item_id'] ?? null;
                                        if ($requestItemId !== null) {
                                            $data['_child_items'][$requestItemId] = $item['child_items'];
                                        }
                                    }
                                    // Always remove child_items from the item data so Filament doesn't try to save it
                                    // This prevents "Array to string conversion" error
                                    unset($data['items'][$index]['child_items']);
                                }
                            }
                            
                            return $data;
                        })
                        ->after(function (BuyerQuote $record, array $data): void {
                            // Process child items from form data
                            if (isset($data['_child_items']) && is_array($data['_child_items'])) {
                                // Get all main items keyed by request_item_id
                                $mainItems = $record->items()
                                    ->whereNotNull('request_item_id')
                                    ->whereHas('requestItem', fn ($q) => $q->whereNull('parent_id'))
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->keyBy('request_item_id');
                                
                                foreach ($data['_child_items'] as $requestItemId => $childItemsData) {
                                    // Match by request_item_id instead of array index for reliability
                                    $mainItem = $mainItems->get($requestItemId);
                                    
                                    if ($mainItem !== null) {
                                        // Delete existing child items for this main item
                                        $childRequestItemIds = $mainItem->requestItem?->children()->pluck('id')->toArray() ?? [];
                                        if (!empty($childRequestItemIds)) {
                                            $record->items()
                                                ->whereIn('request_item_id', $childRequestItemIds)
                                                ->delete();
                                        }
                                        
                                        // Create/update child items
                                        if (is_array($childItemsData) && !empty($childItemsData)) {
                                            foreach ($childItemsData as $childItemData) {
                                                $record->items()->create([
                                                    'request_item_id' => $childItemData['request_item_id'] ?? null,
                                                    'supplier_quote_item_id' => $childItemData['supplier_quote_item_id'] ?? null,
                                                    'description' => $childItemData['description'] ?? '',
                                                    'quantity' => $childItemData['quantity'] ?? '1.0000',
                                                    'unit_of_measure_id' => $childItemData['unit_of_measure_id'] ?? null,
                                                    'cost_price' => $childItemData['cost_price'] ?? '0.0000',
                                                    'unit_price' => $childItemData['unit_price'] ?? '0.0000',
                                                    'unit_price_exc_tax' => $childItemData['unit_price_exc_tax'] ?? '0.0000',
                                                    'tax_code_id' => $childItemData['tax_code_id'] ?? null,
                                                    'tax_rate' => $childItemData['tax_rate'] ?? '0.0000',
                                                    'is_tax_inclusive' => $childItemData['is_tax_inclusive'] ?? false,
                                                    'line_subtotal' => $childItemData['line_subtotal'] ?? '0.0000',
                                                    'line_tax' => $childItemData['line_tax'] ?? '0.0000',
                                                    'line_total' => $childItemData['line_total'] ?? '0.0000',
                                                    'hide_from_pdf' => $childItemData['hide_from_pdf'] ?? false,
                                                    'sort_order' => $mainItem->sort_order + 1,
                                                ]);
                                            }
                                        }
                                    }
                                }
                            }
                            
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

    /**
     * Convert supplier_groups (and nested items/child_items) to use UUID keys so Filament Repeater resolves getChildSchema().
     *
     * @param array<int|string, array<string, mixed>> $groups
     * @return array<string, array<string, mixed>>
     */
    private function supplierGroupsWithUuidKeys(array $groups): array
    {
        $out = [];
        foreach ($groups as $group) {
            $items = $group['items'] ?? [];
            $itemsWithUuids = [];
            foreach ($items as $item) {
                $childItems = $item['child_items'] ?? [];
                $childItemsWithUuids = [];
                foreach ($childItems as $child) {
                    $childItemsWithUuids[(string) Str::uuid()] = $child;
                }
                $item['child_items'] = $childItemsWithUuids;
                $itemsWithUuids[(string) Str::uuid()] = $item;
            }
            $group['items'] = $itemsWithUuids;
            $out[(string) Str::uuid()] = $group;
        }
        return $out;
    }

    /**
     * Build form data for create flow: quote details + supplier_groups (payment terms and items per supplier).
     *
     * @param array<int> $supplierQuoteIds
     * @return array<string, mixed>
     */
    private function buildSupplierGroupsFormData(Request $request, array $supplierQuoteIds): array
    {
        /** @var \App\Models\Team|null $team */
        $team = Filament::getTenant();
        $settings = $team?->getErpSettings();
        $defaultCurrencyCode = $settings->default_currency ?? 'USD';
        $currencyId = Currency::query()
            ->where('code', $defaultCurrencyCode)
            ->where('is_active', true)
            ->value('id');
        $defaultTaxCode = TaxCode::query()
            ->where('team_id', $request->team_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
        $defaultMarginPercent = $settings->default_margin_percent ?? 3.0;
        $defaultPaymentTermsDays = $settings->default_payment_terms_days ?? 30;

        $supplierGroups = [];
        if ($supplierQuoteIds === []) {
            return [
                'status' => BuyerQuoteStatus::DRAFT,
                'currency_id' => $currencyId,
                'exchange_rate' => 1,
                'valid_until' => now()->addDays($settings->quote_validity_days ?? 30),
                'prepayment_type' => PrepaymentType::PERCENT->value,
                'prepayment_amount' => 0,
                '_create_with_supplier_groups' => true,
                'supplier_groups' => [],
            ];
        }
        $supplierQuotes = SupplierQuote::query()
            ->whereIn('id', $supplierQuoteIds)
            ->where('request_id', $request->getKey())
            ->with(['supplier', 'paymentTerms', 'items.requestItem.article', 'items.requestItem.unitOfMeasure', 'items.requestItem.children'])
            ->get()
            ->sortBy(fn (SupplierQuote $sq): int => (int) array_search($sq->id, $supplierQuoteIds, true))
            ->values();

        foreach ($supplierQuotes as $sq) {
            $paymentTerms = $sq->paymentTerms->map(fn (SupplierQuotePaymentTerm $pt): array => [
                'due_days' => $pt->due_days,
                'percentage' => $pt->percentage,
                'job_progress' => $pt->job_progress,
                'sort_order' => $pt->sort_order,
            ])->values()->all();
            if (empty($paymentTerms)) {
                $paymentTerms = [['due_days' => $defaultPaymentTermsDays, 'percentage' => 100, 'job_progress' => null, 'sort_order' => 0]];
            }

            $items = [];
            $sortOrder = 0;
            foreach ($sq->items as $sqi) {
                $requestItem = $sqi->requestItem;
                if ($requestItem === null || $requestItem->parent_id !== null) {
                    continue;
                }
                $costPrice = (float) $sqi->unit_price_exc_tax;
                $taxRate = $defaultTaxCode !== null ? (float) $defaultTaxCode->rate : 0.0;
                $addTax = $defaultTaxCode !== null && $defaultTaxCode->is_inclusive_default;
                $quantity = (float) $requestItem->quantity;
                $unitPriceExcTax = $costPrice > 0 ? round($costPrice * (1 + $defaultMarginPercent / 100), 0) : 0.0;
                $unitPrice = round($unitPriceExcTax, 0);
                $lineSubtotal = $quantity * $unitPriceExcTax;
                $lineTax = $taxRate > 0 && $addTax ? $lineSubtotal * $taxRate / 100 : 0;
                $lineTotal = $addTax && $taxRate > 0 ? $lineSubtotal + $lineTax : $lineSubtotal;
                $marginAmount = $unitPriceExcTax - $costPrice;
                $marginPercent = $costPrice > 0 ? ($marginAmount / $costPrice) * 100 : 0;
                $childItems = [];
                if ($request->isServiceRequest() && $requestItem->children()->exists()) {
                    foreach ($requestItem->children()->orderBy('sort_order')->get() as $childRI) {
                        $childSqi = $sq->items->firstWhere('request_item_id', $childRI->id);
                        $childCost = $childSqi ? (float) $childSqi->unit_price_exc_tax : 0.0;
                        $childQty = (float) $childRI->quantity;
                        $childUnitExcTax = $childCost > 0 ? round($childCost * (1 + $defaultMarginPercent / 100), 0) : 0.0;
                        $childLineSub = $childQty * $childUnitExcTax;
                        $childLineTax = $taxRate > 0 && $addTax ? $childLineSub * $taxRate / 100 : 0;
                        $childLineTotal = $addTax && $taxRate > 0 ? $childLineSub + $childLineTax : $childLineSub;
                        $childItems[] = [
                            'request_item_id' => $childRI->id,
                            'supplier_quote_item_id' => $childSqi?->id,
                            'description' => $childRI->description,
                            'quantity' => (string) $childRI->quantity,
                            'unit_of_measure_id' => $childRI->unit_of_measure_id,
                            'cost_price' => (string) $childCost,
                            'unit_price' => (string) round($childUnitExcTax, 0),
                            'unit_price_exc_tax' => (string) round($childUnitExcTax, 0),
                            'tax_code_id' => $defaultTaxCode?->getKey(),
                            'tax_rate' => (string) $taxRate,
                            'is_tax_inclusive' => $addTax,
                            'line_subtotal' => (string) round($childLineSub, 4),
                            'line_tax' => (string) round($childLineTax, 4),
                            'line_total' => (string) round($childLineTotal, 0),
                            'hide_from_pdf' => false,
                        ];
                    }
                }
                $items[] = [
                    'request_item_id' => $requestItem->id,
                    'article_id' => $requestItem->article_id,
                    'supplier_quote_item_id' => $sqi->id,
                    'from_supplier' => $sq->supplier?->name,
                    'description' => $requestItem->article?->name ?? $requestItem->description,
                    'quantity' => (string) $requestItem->quantity,
                    'unit_of_measure_id' => $requestItem->unit_of_measure_id,
                    'unit' => $requestItem->unitOfMeasure?->code ?? 'pcs',
                    'cost_price' => (string) $costPrice,
                    'unit_price' => (string) $unitPrice,
                    'unit_price_exc_tax' => (string) $unitPriceExcTax,
                    'tax_code_id' => $defaultTaxCode?->getKey(),
                    'tax_rate' => (string) $taxRate,
                    'tax_amount' => (string) round($lineTax / max($quantity, 0.0001), 4),
                    'is_tax_inclusive' => $addTax,
                    'line_subtotal' => (string) round($lineSubtotal, 4),
                    'line_tax' => (string) round($lineTax, 4),
                    'line_total' => (string) round($lineTotal, 0),
                    'margin_amount' => (string) round($marginAmount, 4),
                    'margin_percent' => (string) round($marginPercent, 4),
                    'margin_percent_input' => (int) ($marginPercent > 0 ? round($marginPercent) : round($defaultMarginPercent)),
                    'sort_order' => $sortOrder++,
                    'child_items' => $childItems,
                ];
            }
            $supplierGroups[] = [
                'supplier_quote_id' => $sq->id,
                'supplier_name' => $sq->supplier?->name ?? $sq->quote_number,
                'prepayment_type' => PrepaymentType::PERCENT->value,
                'prepayment_amount' => 0,
                'payment_terms' => $paymentTerms,
                'items' => $items,
            ];
        }

        return [
            'status' => BuyerQuoteStatus::DRAFT,
            'currency_id' => $currencyId,
            'exchange_rate' => 1,
            'valid_until' => now()->addDays($settings->quote_validity_days ?? 30),
            'prepayment_type' => PrepaymentType::PERCENT->value,
            'prepayment_amount' => 0,
            '_create_with_supplier_groups' => true,
            'supplier_groups' => $supplierGroups,
        ];
    }

    /**
     * Create one buyer quote from a single supplier quote (for "create separate" flow).
     */
    private function createBuyerQuoteFromSupplierQuote(Request $request, int $supplierQuoteId): void
    {
        $data = $this->buildSupplierGroupsFormData($request, [$supplierQuoteId]);
        $record = new BuyerQuote();
        $record->request_id = $request->getKey();
        $record->buyer_id = $request->buyer_id;
        $record->status = BuyerQuoteStatus::DRAFT;
        $record->currency_id = $data['currency_id'];
        $record->exchange_rate = (string) ($data['exchange_rate'] ?? 1);
        $record->valid_until = $data['valid_until'] ?? now()->addDays(30);
        $record->prepayment_type = $data['prepayment_type'] ?? PrepaymentType::PERCENT->value;
        $record->prepayment_amount = (string) ($data['prepayment_amount'] ?? 0);
        $record->subtotal = '0';
        $record->tax_total = '0';
        $record->total = '0';
        $record->save();
        $this->saveBuyerQuoteFromSupplierGroups($record, array_merge($data, ['supplier_groups' => $data['supplier_groups'] ?? []]), $request);
    }

    /**
     * Persist payment terms and items from supplier_groups onto an existing buyer quote.
     */
    private function saveBuyerQuoteFromSupplierGroups(BuyerQuote $record, array $data, Request $request): void
    {
        $groups = $data['supplier_groups'] ?? [];
        $defaultTaxCode = TaxCode::query()
            ->where('team_id', $request->team_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
        $sortOrder = 0;
        foreach ($groups as $group) {
            $supplierQuoteId = (int) ($group['supplier_quote_id'] ?? 0);
            foreach ($group['payment_terms'] ?? [] as $pt) {
                $record->paymentTerms()->create([
                    'supplier_quote_id' => $supplierQuoteId ?: null,
                    'due_days' => (int) ($pt['due_days'] ?? 0),
                    'percentage' => (int) ($pt['percentage'] ?? 0),
                    'job_progress' => isset($pt['job_progress']) ? (int) $pt['job_progress'] : null,
                    'sort_order' => $sortOrder++,
                ]);
            }
            foreach ($group['items'] ?? [] as $item) {
                $mainItem = $record->items()->create([
                    'request_item_id' => $item['request_item_id'] ?? null,
                    'article_id' => $item['article_id'] ?? null,
                    'supplier_quote_item_id' => $item['supplier_quote_item_id'] ?? null,
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'] ?? '1',
                    'unit' => $item['unit'] ?? 'pcs',
                    'unit_of_measure_id' => $item['unit_of_measure_id'] ?? null,
                    'cost_price' => $item['cost_price'] ?? '0',
                    'unit_price' => $item['unit_price'] ?? '0',
                    'unit_price_exc_tax' => $item['unit_price_exc_tax'] ?? '0',
                    'tax_code_id' => $item['tax_code_id'] ?? $defaultTaxCode?->getKey(),
                    'tax_rate' => $item['tax_rate'] ?? '0',
                    'is_tax_inclusive' => (bool) ($item['is_tax_inclusive'] ?? false),
                    'line_subtotal' => $item['line_subtotal'] ?? '0',
                    'line_tax' => $item['line_tax'] ?? '0',
                    'line_total' => $item['line_total'] ?? '0',
                    'margin_amount' => $item['margin_amount'] ?? '0',
                    'margin_percent' => $item['margin_percent'] ?? '0',
                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                ]);
                foreach ($item['child_items'] ?? [] as $child) {
                    $record->items()->create([
                        'request_item_id' => $child['request_item_id'] ?? null,
                        'supplier_quote_item_id' => $child['supplier_quote_item_id'] ?? null,
                        'description' => $child['description'] ?? '',
                        'quantity' => $child['quantity'] ?? '1',
                        'unit_of_measure_id' => $child['unit_of_measure_id'] ?? null,
                        'cost_price' => $child['cost_price'] ?? '0',
                        'unit_price' => $child['unit_price'] ?? '0',
                        'unit_price_exc_tax' => $child['unit_price_exc_tax'] ?? '0',
                        'tax_code_id' => $child['tax_code_id'] ?? $defaultTaxCode?->getKey(),
                        'tax_rate' => $child['tax_rate'] ?? '0',
                        'is_tax_inclusive' => (bool) ($child['is_tax_inclusive'] ?? false),
                        'line_subtotal' => $child['line_subtotal'] ?? '0',
                        'line_tax' => $child['line_tax'] ?? '0',
                        'line_total' => $child['line_total'] ?? '0',
                        'hide_from_pdf' => (bool) ($child['hide_from_pdf'] ?? false),
                        'sort_order' => $mainItem->sort_order + 1,
                    ]);
                }
            }
        }
        $record->refresh();
        $record->recalculateTotals();
    }

    /**
     * Calculate item totals based on form values.
     *
     * When is_tax_inclusive is true, unit_price includes tax.
     * When is_tax_inclusive is false, unit_price is net price and tax is added on top.
     *
     * @param bool|null $isTaxInclusiveOverride Optional override for is_tax_inclusive value (useful for nested Repeaters)
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

        // Margin on cost: margin% = (selling_price - cost_price) / cost_price * 100
        $marginAmount = $unitPriceExcTax - $costPrice;
        $marginPercent = $costPrice > 0 ? ($marginAmount / $costPrice) * 100 : 0;

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
