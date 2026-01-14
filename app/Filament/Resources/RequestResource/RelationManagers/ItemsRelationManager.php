<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\RequestStage;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\Article;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
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

final class ItemsRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'items';

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
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->minValue(0.0001)
                    ->default(1),
                TextInput::make('unit')
                    ->maxLength(50)
                    ->default('pcs'),
                Select::make('article_id')
                    ->label('Match to Article')
                    ->columnSpanFull()
                    ->options(function () use ($request): array {
                        // Get all unique articles (no supplier duplicates)
                        return Article::query()
                            ->where('team_id', $request->team_id)
                            ->where('is_active', true)
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (Article $article): array => [
                                $article->getKey() => "[{$article->code}] {$article->name}",
                            ])
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('Select article...')
                    ->helperText('Quotes will be sent to all suppliers of this article. Use + to create a new article.')
                    ->createOptionForm(\App\Filament\Resources\ArticleResource::getFormSchema())
                    ->createOptionUsing(function (array $data) use ($request): int {
                        /** @var Article $article */
                        $article = Article::create([
                            'name' => $data['name'],
                            'sku' => $data['sku'] ?? null,
                            'unit' => $data['unit'] ?? 'pcs',
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

                        return $article->getKey();
                    })
                    ->createOptionModalHeading('Create New Article')
                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                        $set('is_matched', $state !== null);
                    })
                    ->live(),
                Hidden::make('is_matched')
                    ->default(false),
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

        $matchedCount = $request->items()->where('is_matched', true)->count();
        $totalCount = $request->items()->count();
        $allMatched = $matchedCount === $totalCount;

        return $table
            ->recordTitleAttribute('description')
            ->description(function () use ($allMatched, $totalCount, $matchedCount): ?string {
                if ($allMatched || $totalCount === 0) {
                    return null;
                }

                return "Status: {$matchedCount}/{$totalCount} items matched to articles";
            })
            ->modifyQueryUsing(fn ($query) => $query->with('article')->withCount('supplierQuoteItems'))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('description')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (RequestItem $record): ?string => $record->description),
                TextColumn::make('article.code')
                    ->label('Article')
                    ->placeholder('Not matched')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('unit'),
                IconColumn::make('supplier_quote_items_count')
                    ->label('Sent')
                    ->icon(fn (RequestItem $record): ?string => $record->supplier_quote_items_count > 0
                        ? 'heroicon-o-check-circle'
                        : null)
                    ->color('success')
                    ->tooltip(fn (RequestItem $record): ?string => $record->supplier_quote_items_count > 0
                        ? "Sent to {$record->supplier_quote_items_count} supplier(s)"
                        : null)
                    ->width(60),
            ])
            ->filters([
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small)
                    ->visible($canEdit),
                Action::make('sendRequestToAllSuppliers')
                    ->label(fn (): string => count($this->getSelectedTableRecords()) > 0
                        ? 'Send Selected to Suppliers'
                        : 'Send All to Suppliers')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->size(Size::Small)
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
                            $matchedItems = $request->items()->whereNotNull('article_id')->with('article.suppliers')->get();
                        }

                        if ($matchedItems->isEmpty()) {
                            return $hasSelection
                                ? 'None of the selected items are matched to articles.'
                                : 'No items are matched to articles yet.';
                        }

                        $supplierIds = $matchedItems
                            ->flatMap(fn (RequestItem $item) => $item->article?->suppliers?->pluck('id') ?? collect())
                            ->unique()
                            ->count();

                        $prefix = $hasSelection ? 'selected ' : '';

                        return "Send {$matchedItems->count()} {$prefix}matched item(s) to {$supplierIds} supplier(s) for quote requests?";
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
                            $matchedItems = $request->items()
                                ->whereNotNull('article_id')
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

                        DB::transaction(function () use ($matchedItems, $defaultCurrency, $request, &$itemsAdded, &$quotesCreated): void {
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
                                    }

                                    if ($existingQuote->items()->where('request_item_id', $item->getKey())->exists()) {
                                        continue;
                                    }

                                    $existingQuote->items()->create([
                                        'request_item_id' => $item->getKey(),
                                        'article_id' => $item->article_id,
                                        'description' => $item->article?->name ?? $item->description,
                                        'quantity' => $item->quantity,
                                        'unit' => $item->unit,
                                        'sort_order' => $existingQuote->items()->count(),
                                    ]);
                                    $itemsAdded++;
                                }
                            }
                        });

                        // Deselect records after action
                        if ($hasSelection) {
                            $this->deselectAllTableRecords();
                        }

                        if ($itemsAdded > 0 || $quotesCreated > 0) {
                            Notification::make()
                                ->title('Sent to suppliers')
                                ->body("{$quotesCreated} quote(s) created, {$itemsAdded} item(s) added.")
                                ->success()
                                ->send();
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
                    ->modalHeading('Send to All Suppliers')
                    ->modalDescription(function (RequestItem $record): string {
                        $supplierCount = $record->article?->suppliers()->where('companies.is_active', true)->count() ?? 0;

                        if ($supplierCount === 0) {
                            return 'This article has no active suppliers. Add suppliers to the article first.';
                        }

                        return "Send quote request for this item to all {$supplierCount} supplier(s) of this article?";
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

                        DB::transaction(function () use ($suppliers, $record, $request, $defaultCurrency, &$quotesCreated, &$itemsAdded): void {
                            foreach ($suppliers as $supplier) {
                                $existingQuote = $request->supplierQuotes()
                                    ->where('supplier_id', $supplier->getKey())
                                    ->first();

                                if ($existingQuote !== null) {
                                    if ($existingQuote->items()->where('request_item_id', $record->getKey())->exists()) {
                                        continue;
                                    }

                                    $existingQuote->items()->create([
                                        'request_item_id' => $record->getKey(),
                                        'article_id' => $record->article_id,
                                        'description' => $record->article?->name ?? $record->description,
                                        'quantity' => $record->quantity,
                                        'unit' => $record->unit,
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

                                $quote->items()->create([
                                    'request_item_id' => $record->getKey(),
                                    'article_id' => $record->article_id,
                                    'description' => $record->article?->name ?? $record->description,
                                    'quantity' => $record->quantity,
                                    'unit' => $record->unit,
                                    'sort_order' => 0,
                                ]);
                                $itemsAdded++;
                            }
                        });

                        if ($quotesCreated > 0 || $itemsAdded > 0) {
                            Notification::make()
                                ->title('Sent to suppliers')
                                ->body("{$quotesCreated} new quote(s) created, {$itemsAdded} item(s) added.")
                                ->success()
                                ->send();
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
}
