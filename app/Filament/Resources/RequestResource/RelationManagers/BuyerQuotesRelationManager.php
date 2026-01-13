<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\BuyerQuoteStatus;
use App\Filament\Actions\DownloadPdfAction;
use App\Models\BuyerQuote;
use App\Models\Currency;
use App\Models\Request;
use App\Models\TaxCode;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class BuyerQuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'buyerQuotes';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-text';

    public function form(Schema $schema): Schema
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $schema
            ->components([
                Section::make('Quote Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('quote_number_display')
                                    ->label('Quote Number')
                                    ->content(fn (?BuyerQuote $record): string => $record?->quote_number ?? 'Auto-generated'),
                                Placeholder::make('version_display')
                                    ->label('Version')
                                    ->content(fn (?BuyerQuote $record): string => $record instanceof \App\Models\BuyerQuote ? 'v'.$record->version : 'v1'),
                                Select::make('status')
                                    ->options(BuyerQuoteStatus::class)
                                    ->default(BuyerQuoteStatus::DRAFT)
                                    ->required()
                                    ->disabled(fn (?BuyerQuote $record): bool => $record instanceof \App\Models\BuyerQuote && ! $record->status->canEdit()),
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
                                        $defaultCode = auth()->user()->currentTeam?->getErpSettings()->default_currency ?? 'USD';

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
                                DatePicker::make('valid_until')
                                    ->label('Valid Until')
                                    ->helperText('Leave empty for default validity period'),
                            ]),
                    ]),

                Section::make('Payment Terms')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('prepayment_percent')
                                    ->label('Prepayment %')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%'),
                                TextInput::make('payment_terms_days')
                                    ->label('Payment Terms (Days)')
                                    ->numeric()
                                    ->default(30)
                                    ->minValue(0),
                                TextInput::make('payment_terms_description')
                                    ->label('Payment Terms Description')
                                    ->placeholder('e.g., Net 30, 50% upfront'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

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
                                                $requestItem = $request->items()->find($state);
                                                if ($requestItem !== null) {
                                                    $set('article_id', $requestItem->article_id);
                                                    $set('description', $requestItem->description);
                                                    $set('quantity', $requestItem->quantity);
                                                    $set('unit', $requestItem->unit);
                                                }
                                            }),
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
                                        TextInput::make('cost_price')
                                            ->label('Cost Price')
                                            ->numeric()
                                            ->default(0)
                                            ->step(0.0001)
                                            ->columnSpan(2)
                                            ->helperText('From supplier')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Set $set, Get $get) => $this->calculateItemTotals($set, $get)),
                                        TextInput::make('unit_price')
                                            ->label('Selling Price')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->step(0.0001)
                                            ->columnSpan(2)
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
                                            ->searchable()
                                            ->columnSpan(2)
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
                                        Checkbox::make('is_tax_inclusive')
                                            ->label('Tax Inc.')
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
                                        Placeholder::make('margin_display')
                                            ->label('Margin')
                                            ->content(function (Get $get): string {
                                                $costPrice = (float) ($get('cost_price') ?? 0);
                                                $unitPrice = (float) ($get('unit_price') ?? 0);
                                                $marginAmount = $unitPrice - $costPrice;
                                                $marginPercent = $costPrice > 0 ? ($marginAmount / $costPrice) * 100 : 0;

                                                return sprintf('%s (%.1f%%)', number_format($marginAmount, 2), $marginPercent);
                                            })
                                            ->columnSpan(2),
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
                            ->addActionLabel('Add Line Item')
                            ->reorderable('sort_order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['description'] ?? null),
                    ]),

                Section::make('Summary')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(fn (?BuyerQuote $record): string => $record instanceof \App\Models\BuyerQuote
                                        ? number_format((float) $record->subtotal, 2)
                                        : '0.00'),
                                Placeholder::make('tax_total_display')
                                    ->label('Tax Total')
                                    ->content(fn (?BuyerQuote $record): string => $record instanceof \App\Models\BuyerQuote
                                        ? number_format((float) $record->tax_total, 2)
                                        : '0.00'),
                                Placeholder::make('total_display')
                                    ->label('Total')
                                    ->content(fn (?BuyerQuote $record): string => $record instanceof \App\Models\BuyerQuote
                                        ? number_format((float) $record->total, 2)
                                        : '0.00'),
                                Placeholder::make('margin_display')
                                    ->label('Total Margin')
                                    ->content(fn (?BuyerQuote $record): string => $record instanceof \App\Models\BuyerQuote
                                        ? sprintf('%s (%.1f%%)', number_format($record->total_margin_amount, 2), $record->total_margin_percent)
                                        : '0.00 (0.0%)'),
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
            ]);
    }

    public function table(Table $table): Table
    {
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
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->label('Total')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->description(fn (BuyerQuote $record): string => $record->currency?->code ?? ''),
                TextColumn::make('total_margin_amount')
                    ->label('Margin')
                    ->getStateUsing(fn (BuyerQuote $record): string => sprintf(
                        '%s (%.1f%%)',
                        number_format($record->total_margin_amount, 2),
                        $record->total_margin_percent
                    ))
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw(
                        "(SELECT SUM(margin_amount * quantity) FROM buyer_quote_items WHERE buyer_quote_id = buyer_quotes.id) {$direction}"
                    )),
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
                    ->mutateFormDataUsing(function (array $data) use ($request): array {
                        $data['request_id'] = $request->getKey();
                        $data['buyer_id'] = $request->buyer_id;

                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
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
     */
    private function calculateItemTotals(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $costPrice = (float) ($get('cost_price') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);
        $isTaxInclusive = (bool) $get('is_tax_inclusive');

        $lineAmount = $quantity * $unitPrice;

        if ($isTaxInclusive) {
            $lineTotal = $lineAmount;
            $lineSubtotal = $taxRate > 0 ? $lineAmount / (1 + $taxRate / 100) : $lineAmount;
            $lineTax = $lineTotal - $lineSubtotal;
            $unitPriceExcTax = $taxRate > 0 ? $unitPrice / (1 + $taxRate / 100) : $unitPrice;
        } else {
            $lineSubtotal = $lineAmount;
            $lineTax = $lineAmount * $taxRate / 100;
            $lineTotal = $lineSubtotal + $lineTax;
            $unitPriceExcTax = $unitPrice;
        }

        // Calculate margin
        $marginAmount = $unitPriceExcTax - $costPrice;
        $marginPercent = $costPrice > 0 ? ($marginAmount / $costPrice) * 100 : 0;

        $set('unit_price_exc_tax', round($unitPriceExcTax, 4));
        $set('line_subtotal', round($lineSubtotal, 4));
        $set('line_tax', round($lineTax, 4));
        $set('line_total', round($lineTotal, 4));
        $set('margin_amount', round($marginAmount, 4));
        $set('margin_percent', round($marginPercent, 4));
    }
}
