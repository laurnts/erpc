<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\RequestStage;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Mail\Erp\QuoteToSupplierMail;
use App\Models\Article;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use App\Models\UnitOfMeasure;
use App\Services\Email\EmailTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ItemsRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'items';

    /**
     * Temporary storage for children data during edit operations.
     *
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $storedChildrenData = null;

    protected static ?string $title = 'Requested Items';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-queue-list';

    protected static function getAssociatedStage(): RequestStage
    {
        return RequestStage::DRAFT;
    }

    protected static function getBaseTabTitle(): string
    {
        return 'Requested Items';
    }

    public function form(Schema $schema): Schema
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $schema
            ->components([
                TextInput::make('description')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->helperText('Enter a vague description from the buyer.'),
                Select::make('item_type')
                    ->label('Item Type')
                    ->options(\App\Enums\ItemType::class)
                    ->default(\App\Enums\ItemType::GOODS)
                    ->required()
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->columnSpanFull()
                    ->helperText('Goods fulfill via shipments; Services via acceptance reports and support detail items.')
                    ->live(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->minValue(0.0001)
                    ->default(1),
                Select::make('unit_of_measure_id')
                    ->label('Unit of Measure')
                    ->options(
                        fn (): array => UnitOfMeasure::query()
                            ->where('team_id', $request->team_id)
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('label')
                            ->get()
                            ->mapWithKeys(fn (UnitOfMeasure $unit): array => [
                                $unit->getKey() => $unit->label,
                            ])
                            ->toArray())

                    ->preload()
                    ->required()
                    ->selectablePlaceholder(false)
                    ->default(fn (): ?int => UnitOfMeasure::query()
                        ->where('team_id', $request->team_id)
                        ->where('code', 'pcs')
                        ->where('is_active', true)
                        ->value('id'))
                    ->helperText('Select the unit of measure. Use + to create a new unit.')
                    ->createOptionForm(\App\Filament\Resources\UnitOfMeasureResource::getFormSchema())
                    ->createOptionUsing(function (array $data) use ($request): int {
                        /** @var UnitOfMeasure $unit */
                        $unit = UnitOfMeasure::create([
                            'code' => $data['code'],
                            'label' => $data['label'],
                            'sort_order' => $data['sort_order'] ?? 0,
                            'is_active' => $data['is_active'] ?? true,
                            'team_id' => $request->team_id,
                            'creator_id' => auth()->id(),
                        ]);

                        return $unit->getKey();
                    })
                    ->createOptionModalHeading('Create New Unit of Measure'),
                Select::make('article_id')
                    ->label('Match to Article')
                    ->columnSpanFull()
                    ->options(
                        // Get all unique articles (no supplier duplicates)

                        fn (): array => Article::query()
                            ->where('team_id', $request->team_id)
                            ->where('is_active', true)
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (Article $article): array => [
                                $article->getKey() => "[{$article->code}] {$article->name}",
                            ])
                            ->toArray())

                    ->preload()
                    ->searchable()
                    ->selectablePlaceholder(false)
                    ->placeholder('Select article...')
                    ->searchable()
                    ->helperText('Quotes will be sent to all suppliers of this article. Use + to create a new article.')
                    ->createOptionForm(\App\Filament\Resources\ArticleResource::getFormSchema(forModal: true))
                    ->createOptionUsing(function (array $data) use ($request): int {
                        /** @var Article $article */
                        $article = Article::create([
                            'name' => $data['name'],
                            'sku' => $data['sku'] ?? null,
                            'unit' => ! empty($data['unit']) ? $data['unit'] : 'pcs',
                            'default_tax_code_id' => $data['default_tax_code_id'] ?? null,
                            'description' => $data['description'] ?? null,
                            'attributes' => $data['attributes'] ?? null,
                            'team_id' => $request->team_id,
                            'creator_id' => auth()->id(),
                        ]);

                        // Sync tags if provided
                        if (! empty($data['tags'])) {
                            $article->tags()->sync($data['tags']);
                        }

                        // Sync suppliers if provided
                        if (! empty($data['suppliers'])) {
                            $article->suppliers()->sync($data['suppliers']);
                        }

                        return $article->getKey();
                    })
                    ->createOptionModalHeading('Create New Article')
                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                        $set('is_matched', $state !== null);
                    })
                    ->live(),
                Hidden::make('is_matched')
                    ->default(false),
                Hidden::make('parent_id')
                    ->default(null),
                // Child items section for Service requests
                Repeater::make('children')
                    ->label('Detail Items')
                    ->schema([
                        TextInput::make('description')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Detail description of the child item'),
                        TextInput::make('quantity')
                            ->required()
                            ->numeric(),
                        Select::make('unit_of_measure_id')
                            ->label('Unit of Measure')
                            ->options(
                                fn (): array => UnitOfMeasure::query()
                                    ->where('team_id', $request->team_id)
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->orderBy('label')
                                    ->get()
                                    ->mapWithKeys(fn (UnitOfMeasure $unit): array => [
                                        $unit->getKey() => $unit->label,
                                    ])
                                    ->toArray())
                            ->preload()
                            ->required()
                            ->default(fn (): ?int => UnitOfMeasure::query()
                                ->where('team_id', $request->team_id)
                                ->where('code', 'pcs')
                                ->where('is_active', true)
                                ->value('id')),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn ($get, $record): bool => self::isServiceItemState($get('item_type')) && ($get('article_id') !== null || ($record && $record->article_id !== null)))
                    ->helperText('Add child items to provide detail breakdown of the service (services items only)')
                    ->defaultItems(0)
                    ->collapsible()
                    ->dehydrated(true), // Ensure Repeater data is included in form submission
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();
        $canEdit = $request->canEditItems();

        // Only count main items, not child items
        // A matched item must have both is_matched = true AND article_id is not null
        $matchedCount = $request->items()
            ->whereNull('parent_id')
            ->where('is_matched', true)
            ->whereNotNull('article_id')
            ->count();
        $totalCount = $request->items()->whereNull('parent_id')->count();
        $allMatched = $matchedCount === $totalCount && $totalCount > 0;

        return $table
            ->recordTitleAttribute('description')
            ->description(function () use ($totalCount, $matchedCount): ?string {
                if ($totalCount === 0) {
                    return null;
                }

                return "Status: {$matchedCount}/{$totalCount} items matched to articles";
            })
            ->modifyQueryUsing(fn ($query) => $query->whereNull('parent_id')->with(['article', 'children', 'supplierQuoteItems.supplierQuote'])->withCount('supplierQuoteItems'))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('description')

                    ->limit(50)
                    ->tooltip(fn (?RequestItem $record): ?string => $record?->description),
                TextColumn::make('item_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('article.code')
                    ->label('Article')
                    ->placeholder('Not matched')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('unitOfMeasure.label')
                    ->label('Unit'),
                IconColumn::make('supplier_quote_items_count')
                    ->label('Sent')
                    ->icon(fn (RequestItem $record): ?string => $record->supplier_quote_items_count > 0
                        ? 'heroicon-o-check-circle'
                        : null)
                    ->color(function (RequestItem $record): string {
                        if ($record->supplier_quote_items_count === 0) {
                            return 'gray';
                        }

                        // Check if any emails failed
                        $hasFailedEmails = false;
                        $hasSuccessfulEmails = false;

                        foreach ($record->supplierQuoteItems as $quoteItem) {
                            $quote = $quoteItem->supplierQuote ?? null;
                            if ($quote && isset($quote->notification_metadata['email_sent'])) {
                                if ($quote->notification_metadata['email_sent'] === true) {
                                    $hasSuccessfulEmails = true;
                                } elseif ($quote->notification_metadata['email_sent'] === false) {
                                    $hasFailedEmails = true;
                                }
                            }
                        }

                        // If there are failed emails, show warning color
                        if ($hasFailedEmails) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->tooltip(function (RequestItem $record): ?string {
                        if ($record->supplier_quote_items_count === 0) {
                            return null;
                        }

                        $tooltip = "Sent to {$record->supplier_quote_items_count} supplier(s)";

                        // Check email status
                        $emailsSent = 0;
                        $emailsFailed = 0;
                        $noEmailStatus = 0;

                        foreach ($record->supplierQuoteItems as $quoteItem) {
                            $quote = $quoteItem->supplierQuote ?? null;
                            if ($quote && isset($quote->notification_metadata['email_sent'])) {
                                if ($quote->notification_metadata['email_sent'] === true) {
                                    $emailsSent++;
                                } elseif ($quote->notification_metadata['email_sent'] === false) {
                                    $emailsFailed++;
                                    if (isset($quote->notification_metadata['email_error'])) {
                                        // Email failed
                                    }
                                }
                            } else {
                                $noEmailStatus++;
                            }
                        }

                        if ($emailsSent > 0 || $emailsFailed > 0) {
                            $tooltip .= "\n\nEmail Status:";
                            if ($emailsSent > 0) {
                                $tooltip .= "\n✓ {$emailsSent} email(s) sent successfully";
                            }
                            if ($emailsFailed > 0) {
                                $tooltip .= "\n✗ {$emailsFailed} email(s) failed to send";
                            }
                            if ($noEmailStatus > 0) {
                                $tooltip .= "\n? {$noEmailStatus} quote(s) created before email tracking";
                            }
                        }

                        return $tooltip;
                    })
                    ->width(60),
            ])
            ->filters([
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small)
                    ->visible($canEdit)
                    ->using(function (array $data) use ($request): RequestItem {
                        // Store children data before removing it
                        $childrenData = $data['children'] ?? [];
                        unset($data['children']);

                        // Ensure request_id is set
                        $data['request_id'] = $request->id;

                        // Create the main item
                        $record = RequestItem::create($data);

                        // Child items only exist when the form offered them (services items)
                        if (! empty($childrenData)) {
                            $sortOrder = 0;
                            foreach ($childrenData as $childData) {
                                RequestItem::create([
                                    'request_id' => $request->id,
                                    'parent_id' => $record->id,
                                    'description' => $childData['description'],
                                    'quantity' => $childData['quantity'],
                                    'unit_of_measure_id' => $childData['unit_of_measure_id'],
                                    'sort_order' => $sortOrder++,
                                    'is_matched' => false, // Child items don't need article matching
                                ]);
                            }
                        }

                        return $record;
                    }),
                Action::make('sendRequestToAllSuppliers')
                    ->label(fn (): string => count($this->getSelectedTableRecords()) > 0
                        ? 'Send Selected to Suppliers'
                        : 'Send All to Suppliers')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->size(Size::Small)
                    ->visible(fn () => $request->items()->count() > 0)
                    ->requiresConfirmation()
                    ->modalHeading(fn (): string => count($this->getSelectedTableRecords()) > 0
                        ? 'Send Selected Items to Suppliers'
                        : 'Send All Items to Suppliers')
                    ->modalDescription(function () use ($request): string {
                        $selectedRecords = $this->getSelectedTableRecords();
                        $hasSelection = count($selectedRecords) > 0;

                        if ($hasSelection) {
                            $selectedIds = collect($selectedRecords)
                                ->filter(fn (RequestItem $item): bool => $item->article_id !== null)
                                ->pluck('id');
                            $matchedItems = RequestItem::whereIn('id', $selectedIds)
                                ->with('article.suppliers')
                                ->get();
                        } else {
                            // Child items are detail breakdown only — never sent to suppliers
                            $matchedItems = $request->items()
                                ->whereNotNull('article_id')
                                ->whereNull('parent_id')
                                ->with('article.suppliers')
                                ->get();
                        }

                        if ($matchedItems->isEmpty()) {
                            return $hasSelection
                                ? 'None of the selected items are matched to articles.'
                                : 'No items are matched to articles yet. Please match items to articles first.';
                        }

                        $supplierIds = $matchedItems
                            ->flatMap(fn (RequestItem $item) => $item->article?->suppliers?->pluck('id') ?? collect())
                            ->unique()
                            ->count();

                        $itemsList = $matchedItems->take(5)->map(fn (RequestItem $item): string => "• {$item->article?->name} (".number_format((float) $item->quantity, 0).' '.($item->unitOfMeasure?->code ?? $item->unit?->value ?? 'pcs').')')->implode("\n");
                        $moreItems = $matchedItems->count() > 5 ? "\n• ... and ".($matchedItems->count() - 5).' more item(s)' : '';

                        $prefix = $hasSelection ? 'SELECTED ' : 'ALL ';

                        $hasExistingQuotes = $matchedItems->some(fn (RequestItem $item) => $item->supplier_quote_items_count > 0);
                        $resendNote = $hasExistingQuotes ? "\n\n📧 If emails failed previously, they will be resent automatically." : "\n\n📧 Email notifications will be sent to suppliers for newly created quote requests.";

                        return "You are about to send {$prefix}matched items to their respective suppliers.\n\n{$itemsList}{$moreItems}\n\nTotal: {$matchedItems->count()} item(s) will be sent to {$supplierIds} supplier(s).{$resendNote}";
                    })
                    ->action(function () use ($request): void {
                        $selectedRecords = $this->getSelectedTableRecords();
                        $hasSelection = count($selectedRecords) > 0;

                        if ($hasSelection) {
                            $selectedIds = collect($selectedRecords)
                                ->filter(fn (RequestItem $item): bool => $item->article_id !== null)
                                ->pluck('id');
                            $matchedItems = RequestItem::whereIn('id', $selectedIds)
                                ->with('article.suppliers')
                                ->get();
                        } else {
                            // Child items are detail breakdown only — never sent to suppliers
                            $matchedItems = $request->items()
                                ->whereNotNull('article_id')
                                ->whereNull('parent_id')
                                ->with('article.suppliers')
                                ->get();
                        }

                        if ($matchedItems->isEmpty()) {
                            Notification::make()
                                ->title('No matched items')
                                ->body($hasSelection
                                    ? 'None of the selected items are matched to articles.'
                                    : 'Match items to articles before sending to suppliers.')
                                ->warning()
                                ->send();

                            return;
                        }

                        /** @var \App\Models\Team|null $team */
                        $team = Filament::getTenant();
                        $defaultCurrencyCode = $team?->getErpSettings()->default_currency ?? 'USD';
                        $defaultCurrency = Currency::query()
                            ->where('code', $defaultCurrencyCode)
                            ->where('is_active', true)
                            ->first();

                        $itemsAdded = 0;
                        $quotesCreated = 0;
                        $quotesToEmail = [];

                        DB::transaction(function () use ($matchedItems, $defaultCurrency, $request, &$itemsAdded, &$quotesCreated, &$quotesToEmail): void {
                            foreach ($matchedItems as $item) {
                                /** @var RequestItem $item */
                                if ($item->article === null) {
                                    continue;
                                }

                                $suppliers = $item->article->suppliers()
                                    ->where('companies.is_active', true)
                                    ->get();

                                foreach ($suppliers as $supplier) {
                                    $existingQuote = $request->supplierQuotes()
                                        ->where('supplier_id', $supplier->getKey())
                                        ->first();

                                    if ($existingQuote === null) {
                                        /** @var SupplierQuote $existingQuote */
                                        $existingQuote = $request->supplierQuotes()->create([
                                            'supplier_id' => $supplier->getKey(),
                                            'currency_id' => $defaultCurrency?->getKey(),
                                            'exchange_rate' => 1,
                                            'quoted_at' => now(),
                                        ]);
                                        $quotesCreated++;
                                        $quotesToEmail[] = $existingQuote;
                                    } else {
                                        // Refresh to ensure notification_metadata is loaded
                                        $existingQuote->refresh();
                                        // Always check if email needs to be resent (failed or not sent)
                                        $needsResend = false;
                                        $metadata = $existingQuote->notification_metadata ?? [];
                                        if (! isset($metadata['email_sent'])) {
                                            $needsResend = true; // Email was never sent
                                        } elseif ($metadata['email_sent'] === false) {
                                            $needsResend = true; // Email failed previously
                                        }

                                        if ($needsResend) {
                                            $quoteIds = array_map(fn ($q) => is_object($q) ? $q->id : $q, $quotesToEmail);
                                            if (! in_array($existingQuote->id, $quoteIds)) {
                                                $quotesToEmail[] = $existingQuote;
                                            }
                                        }
                                    }

                                    if ($existingQuote->items()->where('request_item_id', $item->getKey())->exists()) {
                                        continue;
                                    }

                                    $existingQuote->items()->create([
                                        'request_item_id' => $item->getKey(),
                                        'article_id' => $item->article_id,
                                        'description' => $item->article?->name ?? $item->description,
                                        'quantity' => $item->quantity,
                                        'unit' => $item->unit?->value ?? 'pcs',
                                        'sort_order' => $existingQuote->items()->count(),
                                    ]);
                                    $itemsAdded++;
                                }
                            }
                        });

                        // Also check all existing quotes for matched items to resend emails if needed
                        if (empty($quotesToEmail) && ! empty($matchedItems)) {
                            $itemIds = $matchedItems->pluck('id')->toArray();
                            $existingQuotes = $request->supplierQuotes()
                                ->whereHas('items', function ($query) use ($itemIds) {
                                    $query->whereIn('request_item_id', $itemIds);
                                })
                                ->get();

                            foreach ($existingQuotes as $quote) {
                                // Refresh to ensure notification_metadata is loaded
                                $quote->refresh();
                                $metadata = $quote->notification_metadata ?? [];
                                $needsResend = false;
                                if (! isset($metadata['email_sent'])) {
                                    $needsResend = true; // Email was never sent
                                } elseif ($metadata['email_sent'] === false) {
                                    $needsResend = true; // Email failed previously
                                }

                                if ($needsResend) {
                                    $quoteIds = array_map(fn ($q) => is_object($q) ? $q->id : $q, $quotesToEmail);
                                    if (! in_array($quote->id, $quoteIds)) {
                                        $quotesToEmail[] = $quote;
                                    }
                                }
                            }
                        }

                        // Send emails to suppliers for new quotes and resend for existing quotes with failed emails
                        $emailsSent = 0;
                        $emailsFailed = 0;
                        $noEmailCount = 0;
                        $emailsResent = 0;

                        if (! empty($quotesToEmail) && $team !== null) {
                            $emailService = app(EmailTemplateService::class);
                            $settings = $team->getErpSettings();

                            foreach ($quotesToEmail as $quote) {
                                // Check if this is a resend
                                $isResend = isset($quote->notification_metadata['email_sent']) &&
                                           $quote->notification_metadata['email_sent'] === false;

                                // Reload quote with relationships for email
                                $quote->load(['supplier', 'request', 'team']);

                                $supplierEmail = $quote->supplier->email ?? null;
                                $supplierName = $quote->supplier->name ?? 'Supplier';

                                if (empty($supplierEmail)) {
                                    $noEmailCount++;
                                    // Track that email was not sent due to missing email
                                    $quote->update([
                                        'notification_metadata' => array_merge(
                                            $quote->notification_metadata ?? [],
                                            [
                                                'email_sent' => false,
                                                'email_sent_at' => null,
                                                'email_error' => 'Supplier has no email address configured',
                                            ]
                                        ),
                                    ]);

                                    continue;
                                }

                                try {
                                    $emailService->sendWithTeamSettings(
                                        $team,
                                        new QuoteToSupplierMail($quote),
                                        $supplierEmail,
                                        $settings->email_template_quote_to_supplier ?? null
                                    );
                                    $emailsSent++;
                                    if ($isResend) {
                                        $emailsResent++;
                                    }
                                    // Track successful email send
                                    $quote->update([
                                        'notification_metadata' => array_merge(
                                            $quote->notification_metadata ?? [],
                                            [
                                                'email_sent' => true,
                                                'email_sent_at' => now()->toIso8601String(),
                                                'email_error' => null,
                                            ]
                                        ),
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error('Failed to send quote request email to supplier', [
                                        'quote_id' => $quote->id,
                                        'supplier_id' => $quote->supplier_id,
                                        'supplier_email' => $supplierEmail,
                                        'error' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString(),
                                    ]);
                                    $emailsFailed++;
                                    // Track failed email send
                                    $quote->update([
                                        'notification_metadata' => array_merge(
                                            $quote->notification_metadata ?? [],
                                            [
                                                'email_sent' => false,
                                                'email_sent_at' => null,
                                                'email_error' => $e->getMessage(),
                                            ]
                                        ),
                                    ]);
                                }
                            }
                        }

                        // Deselect records after action
                        if ($hasSelection) {
                            $this->deselectAllTableRecords();
                        }

                        if ($itemsAdded > 0 || $quotesCreated > 0 || $emailsSent > 0) {
                            $message = '';

                            if ($quotesCreated > 0) {
                                $message .= "{$quotesCreated} quote(s) created. ";
                            }

                            if ($itemsAdded > 0) {
                                $message .= "{$itemsAdded} item(s) added. ";
                            }

                            if ($emailsSent > 0) {
                                if ($emailsResent > 0) {
                                    $message .= "{$emailsResent} email(s) resent, ".($emailsSent - $emailsResent).' email(s) sent to suppliers.';
                                } else {
                                    $message .= "{$emailsSent} email(s) sent to suppliers.";
                                }
                            }

                            if ($emailsFailed > 0) {
                                $message .= " {$emailsFailed} email(s) failed to send.";
                            }

                            if ($noEmailCount > 0) {
                                $message .= " {$noEmailCount} supplier(s) have no email address configured.";
                            }

                            $notification = Notification::make()
                                ->title('Sent to suppliers')
                                ->body(trim($message));

                            if ($emailsFailed > 0 || $noEmailCount > 0) {
                                $notification->warning();
                            } else {
                                $notification->success();
                            }

                            $notification->send();
                        } else {
                            Notification::make()
                                ->title('No changes')
                                ->body('All items are already in quote requests or have no suppliers.')
                                ->info()
                                ->send();
                        }
                    }),
            ])
            ->recordAction('edit')
            ->recordActions([
                EditAction::make()
                    ->label('')
                    ->icon('')
                    ->size(Size::Small)
                    ->visible(fn (RequestItem $record): bool => $record->isMainItem())
                    ->fillForm(function (RequestItem $record): array {
                        // Load existing children for editing
                        $data = $record->toArray();
                        if ($record->isMainItem()) {
                            $data['children'] = $record->children()->get()->map(function (RequestItem $child): array {
                                return [
                                    'description' => $child->description,
                                    'quantity' => $child->quantity,
                                    'unit_of_measure_id' => $child->unit_of_measure_id,
                                ];
                            })->toArray();
                        } else {
                            $data['children'] = [];
                        }

                        return $data;
                    })
                    ->using(function (array $data, RequestItem $record) use ($request): RequestItem {
                        // Get children data before removing it
                        $childrenData = $data['children'] ?? [];

                        // Also try to get from livewire form state as fallback
                        if (empty($childrenData)) {
                            try {
                                $livewire = $this->getLivewire();
                                if (method_exists($livewire, 'form')) {
                                    $formState = $livewire->form->getState();
                                    $childrenData = $formState['children'] ?? [];
                                }
                            } catch (\Exception $e) {
                                // Ignore
                            }
                        }

                        // Remove children from data before updating (it's not a model field)
                        unset($data['children']);

                        // Update the record
                        $record->update($data);

                        // Goods items never carry children — a services→goods switch drops
                        // any child data regardless of what the hidden repeater submitted
                        if (! $record->refresh()->supportsItemHierarchy()) {
                            $childrenData = [];
                        }

                        // Re-sync children from the submitted data
                        if ($record->isMainItem()) {
                            // Delete existing children
                            $record->children()->delete();

                            // Create new children
                            if (! empty($childrenData) && is_array($childrenData)) {
                                $sortOrder = 0;
                                foreach ($childrenData as $childData) {
                                    // Validate child data structure
                                    if (is_array($childData) && isset($childData['description']) && ! empty($childData['description'])) {
                                        RequestItem::create([
                                            'request_id' => $request->id,
                                            'parent_id' => $record->id,
                                            'description' => $childData['description'],
                                            'quantity' => $childData['quantity'] ?? 1,
                                            'unit_of_measure_id' => $childData['unit_of_measure_id'] ?? null,
                                            'sort_order' => $sortOrder++,
                                            'is_matched' => false,
                                        ]);
                                    }
                                }
                            }
                        }

                        return $record;
                    })
                    ->extraModalFooterActions(fn (): array => $canEdit ? [
                        DeleteAction::make(),
                    ] : []),
                Action::make('sendToSuppliers')
                    ->label('Send to Suppliers')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->size(Size::Small)
                    ->visible(fn (RequestItem $record): bool => $record->article_id !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Send Single Item to Suppliers')
                    ->modalDescription(function (RequestItem $record): string {
                        $supplierCount = $record->article?->suppliers()->where('companies.is_active', true)->count() ?? 0;
                        $articleName = $record->article?->name ?? $record->description;

                        if ($supplierCount === 0) {
                            return "The article \"{$articleName}\" has no active suppliers. Add suppliers to the article first.";
                        }

                        $qty = number_format((float) $record->quantity, 0);

                        $hasExistingQuotes = $record->supplier_quote_items_count > 0;
                        $resendNote = $hasExistingQuotes ? "\n\n📧 If emails failed previously, they will be resent automatically." : "\n\n📧 Email notifications will be sent to suppliers for newly created quote requests.";

                        return "You are about to send a quote request for:\n\n• Item: {$articleName}\n• Quantity: {$qty} ".($record->unitOfMeasure?->code ?? $record->unit?->value ?? 'pcs')."\n\nThis will be sent to {$supplierCount} supplier(s) linked to this article.{$resendNote}";
                    })
                    ->action(function (RequestItem $record) use ($request): void {
                        if ($record->article === null) {
                            return;
                        }

                        $suppliers = $record->article->suppliers()
                            ->where('companies.is_active', true)
                            ->get();

                        if ($suppliers->isEmpty()) {
                            Notification::make()
                                ->title('No suppliers')
                                ->body('This article has no active suppliers.')
                                ->warning()
                                ->send();

                            return;
                        }

                        /** @var \App\Models\Team|null $team */
                        $team = Filament::getTenant();
                        $defaultCurrencyCode = $team?->getErpSettings()->default_currency ?? 'USD';
                        $defaultCurrency = Currency::query()
                            ->where('code', $defaultCurrencyCode)
                            ->where('is_active', true)
                            ->first();

                        $quotesCreated = 0;
                        $itemsAdded = 0;
                        $quotesToEmail = [];

                        DB::transaction(function () use ($suppliers, $record, $request, $defaultCurrency, &$quotesCreated, &$itemsAdded, &$quotesToEmail): void {
                            foreach ($suppliers as $supplier) {
                                $existingQuote = $request->supplierQuotes()
                                    ->where('supplier_id', $supplier->getKey())
                                    ->first();

                                if ($existingQuote !== null) {
                                    // Always check if email needs to be resent (failed or not sent)
                                    $needsResend = false;
                                    $metadata = $existingQuote->notification_metadata ?? [];
                                    if (! isset($metadata['email_sent'])) {
                                        $needsResend = true; // Email was never sent
                                    } elseif ($metadata['email_sent'] === false) {
                                        $needsResend = true; // Email failed previously
                                    }

                                    if ($needsResend) {
                                        $quoteIds = array_map(fn ($q) => is_object($q) ? $q->id : $q, $quotesToEmail);
                                        if (! in_array($existingQuote->id, $quoteIds)) {
                                            $quotesToEmail[] = $existingQuote;
                                        }
                                    }

                                    if ($existingQuote->items()->where('request_item_id', $record->getKey())->exists()) {
                                        continue;
                                    }

                                    $existingQuote->items()->create([
                                        'request_item_id' => $record->getKey(),
                                        'article_id' => $record->article_id,
                                        'description' => $record->article?->name ?? $record->description,
                                        'quantity' => $record->quantity,
                                        'unit_of_measure_id' => $record->unit_of_measure_id,
                                        'unit' => $record->unitOfMeasure?->code ?? $record->unit?->value ?? 'pcs',
                                        'sort_order' => $existingQuote->items()->count(),
                                    ]);
                                    $itemsAdded++;

                                    continue;
                                }

                                /** @var SupplierQuote $quote */
                                $quote = $request->supplierQuotes()->create([
                                    'supplier_id' => $supplier->getKey(),
                                    'currency_id' => $defaultCurrency?->getKey(),
                                    'exchange_rate' => 1,
                                    'quoted_at' => now(),
                                ]);
                                $quotesCreated++;
                                $quotesToEmail[] = $quote;

                                $quote->items()->create([
                                    'request_item_id' => $record->getKey(),
                                    'article_id' => $record->article_id,
                                    'description' => $record->article?->name ?? $record->description,
                                    'quantity' => $record->quantity,
                                    'unit_of_measure_id' => $record->unit_of_measure_id,
                                    'unit' => $record->unitOfMeasure?->code ?? $record->unit?->value ?? 'pcs',
                                    'sort_order' => 0,
                                ]);
                                $itemsAdded++;
                            }
                        });

                        // Also check existing quotes for this item to resend emails if needed
                        if (empty($quotesToEmail)) {
                            $existingQuotes = $request->supplierQuotes()
                                ->whereHas('items', function ($query) use ($record) {
                                    $query->where('request_item_id', $record->getKey());
                                })
                                ->get();

                            foreach ($existingQuotes as $quote) {
                                // Refresh to ensure notification_metadata is loaded
                                $quote->refresh();
                                $metadata = $quote->notification_metadata ?? [];
                                $needsResend = false;
                                if (! isset($metadata['email_sent'])) {
                                    $needsResend = true; // Email was never sent
                                } elseif ($metadata['email_sent'] === false) {
                                    $needsResend = true; // Email failed previously
                                }

                                if ($needsResend) {
                                    $quoteIds = array_map(fn ($q) => is_object($q) ? $q->id : $q, $quotesToEmail);
                                    if (! in_array($quote->id, $quoteIds)) {
                                        $quotesToEmail[] = $quote;
                                    }
                                }
                            }
                        }

                        // Send emails to suppliers for new quotes and resend for existing quotes with failed emails
                        $emailsSent = 0;
                        $emailsFailed = 0;
                        $noEmailCount = 0;
                        $emailsResent = 0;

                        if (! empty($quotesToEmail) && $team !== null) {
                            $emailService = app(EmailTemplateService::class);
                            $settings = $team->getErpSettings();

                            foreach ($quotesToEmail as $quote) {
                                // Check if this is a resend
                                $isResend = isset($quote->notification_metadata['email_sent']) &&
                                           $quote->notification_metadata['email_sent'] === false;

                                // Reload quote with relationships for email
                                $quote->load(['supplier', 'request', 'team']);

                                $supplierEmail = $quote->supplier->email ?? null;
                                $supplierName = $quote->supplier->name ?? 'Supplier';

                                if (empty($supplierEmail)) {
                                    $noEmailCount++;
                                    // Track that email was not sent due to missing email
                                    $quote->update([
                                        'notification_metadata' => array_merge(
                                            $quote->notification_metadata ?? [],
                                            [
                                                'email_sent' => false,
                                                'email_sent_at' => null,
                                                'email_error' => 'Supplier has no email address configured',
                                            ]
                                        ),
                                    ]);

                                    continue;
                                }

                                try {
                                    $emailService->sendWithTeamSettings(
                                        $team,
                                        new QuoteToSupplierMail($quote),
                                        $supplierEmail,
                                        $settings->email_template_quote_to_supplier ?? null
                                    );
                                    $emailsSent++;
                                    if ($isResend) {
                                        $emailsResent++;
                                    }
                                    // Track successful email send
                                    $quote->update([
                                        'notification_metadata' => array_merge(
                                            $quote->notification_metadata ?? [],
                                            [
                                                'email_sent' => true,
                                                'email_sent_at' => now()->toIso8601String(),
                                                'email_error' => null,
                                            ]
                                        ),
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error('Failed to send quote request email to supplier', [
                                        'quote_id' => $quote->id,
                                        'supplier_id' => $quote->supplier_id,
                                        'supplier_email' => $supplierEmail,
                                        'error' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString(),
                                    ]);
                                    $emailsFailed++;
                                    // Track failed email send
                                    $quote->update([
                                        'notification_metadata' => array_merge(
                                            $quote->notification_metadata ?? [],
                                            [
                                                'email_sent' => false,
                                                'email_sent_at' => null,
                                                'email_error' => $e->getMessage(),
                                            ]
                                        ),
                                    ]);
                                }
                            }
                        }

                        if ($quotesCreated > 0 || $itemsAdded > 0 || $emailsSent > 0) {
                            $message = '';

                            if ($quotesCreated > 0) {
                                $message .= "{$quotesCreated} new quote(s) created. ";
                            }

                            if ($itemsAdded > 0) {
                                $message .= "{$itemsAdded} item(s) added. ";
                            }

                            if ($emailsSent > 0) {
                                if ($emailsResent > 0) {
                                    $message .= "{$emailsResent} email(s) resent, ".($emailsSent - $emailsResent).' email(s) sent to suppliers.';
                                } else {
                                    $message .= "{$emailsSent} email(s) sent to suppliers.";
                                }
                            }

                            if ($emailsFailed > 0) {
                                $message .= " {$emailsFailed} email(s) failed to send.";
                            }

                            if ($noEmailCount > 0) {
                                $message .= " {$noEmailCount} supplier(s) have no email address configured.";
                            }

                            $notification = Notification::make()
                                ->title('Sent to suppliers')
                                ->body(trim($message));

                            if ($emailsFailed > 0 || $noEmailCount > 0) {
                                $notification->warning();
                            } else {
                                $notification->success();
                            }

                            $notification->send();
                        } else {
                            Notification::make()
                                ->title('No changes')
                                ->body('Item is already in quote requests for all suppliers.')
                                ->info()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()->visible($canEdit),
            ]);
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Request $ownerRecord */
        if ($ownerRecord->items()->doesntExist()) {
            return null;
        }

        return $ownerRecord->all_items_matched ? '✓' : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Request $ownerRecord */
        return $ownerRecord->all_items_matched ? 'success' : null;
    }

    /**
     * Form state for item_type may hold the enum instance or its backing value.
     */
    private static function isServiceItemState(mixed $state): bool
    {
        $type = $state instanceof \App\Enums\ItemType
            ? $state
            : \App\Enums\ItemType::tryFrom((string) ($state ?? ''));

        return $type === \App\Enums\ItemType::SERVICE;
    }
}
