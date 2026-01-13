<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\OrderStatus;
use App\Enums\SupplierQuoteStatus;
use App\Filament\Actions\DownloadPdfAction;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\SupplierQuote;
use App\Models\TaxCode;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class SupplierOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'supplierOrders';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-shopping-cart';

    public function form(Schema $schema): Schema
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $schema
            ->columns(1)
            ->components([
                Section::make('Order Details')
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
                                    ->required(),
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
                                            ->searchable()
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
                                        TextInput::make('tax_rate')
                                            ->label('Tax %')
                                            ->numeric()
                                            ->default(0)
                                            ->step(0.01)
                                            ->columnSpan(2)
                                            ->disabled()
                                            ->dehydrated(),
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
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(fn (?SupplierOrder $record): string => $record instanceof \App\Models\SupplierOrder
                                        ? number_format((float) $record->subtotal, 2)
                                        : '0.00'),
                                Placeholder::make('tax_total_display')
                                    ->label('Tax Total')
                                    ->content(fn (?SupplierOrder $record): string => $record instanceof \App\Models\SupplierOrder
                                        ? number_format((float) $record->tax_total, 2)
                                        : '0.00'),
                                Placeholder::make('total_display')
                                    ->label('Total')
                                    ->content(fn (?SupplierOrder $record): string => $record instanceof \App\Models\SupplierOrder
                                        ? number_format((float) $record->total, 2)
                                        : '0.00'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('base_subtotal_display')
                                    ->label('Subtotal (Base)')
                                    ->content(fn (?SupplierOrder $record): string => $record instanceof \App\Models\SupplierOrder
                                        ? number_format((float) $record->base_subtotal, 2)
                                        : '0.00'),
                                Placeholder::make('base_tax_total_display')
                                    ->label('Tax Total (Base)')
                                    ->content(fn (?SupplierOrder $record): string => $record instanceof \App\Models\SupplierOrder
                                        ? number_format((float) $record->base_tax_total, 2)
                                        : '0.00'),
                                Placeholder::make('base_total_display')
                                    ->label('Total (Base)')
                                    ->content(fn (?SupplierOrder $record): string => $record instanceof \App\Models\SupplierOrder
                                        ? number_format((float) $record->base_total, 2)
                                        : '0.00'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Notes')
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
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->description(fn (SupplierOrder $record): string => $record->currency->code ?? ''),
                TextColumn::make('base_total')
                    ->label('Total (Base)')
                    ->numeric(decimalPlaces: 2)
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
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small),
                Action::make('createFromQuote')
                    ->label('Create from Quote')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->size(Size::Small)
                    ->form([
                        Select::make('supplier_quote_id')
                            ->label('Select Quote')
                            ->options(function (): array {
                                /** @var Request $request */
                                $request = $this->getOwnerRecord();

                                return SupplierQuote::query()
                                    ->where('request_id', $request->getKey())
                                    ->where('status', SupplierQuoteStatus::SELECTED)
                                    ->with('supplier')
                                    ->get()
                                    ->mapWithKeys(fn (SupplierQuote $quote): array => [
                                        $quote->getKey() => "[{$quote->quote_number}] {$quote->supplier->name} - ".number_format((float) $quote->total, 2),
                                    ])
                                    ->all();
                            })
                            ->required()
                            ->searchable()
                            ->helperText('Select a quote to create a purchase order from'),
                    ])
                    ->action(function (array $data): void {
                        $quote = SupplierQuote::with('items')->findOrFail($data['supplier_quote_id']);
                        $order = SupplierOrder::createFromQuote($quote);

                        Notification::make()
                            ->title('Purchase order created')
                            ->body("PO #{$order->po_number} created from quote #{$quote->quote_number}")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
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
                Action::make('markOrdered')
                    ->label('Mark as Sent')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (SupplierOrder $record): bool => $record->ordered_at === null && $record->status !== OrderStatus::CANCELLED)
                    ->requiresConfirmation()
                    ->modalHeading('Mark order as sent?')
                    ->modalDescription('This will record that the PO has been sent to the supplier.')
                    ->action(function (SupplierOrder $record): void {
                        $record->markAsOrdered();
                        Notification::make()
                            ->title('Order marked as sent')
                            ->success()
                            ->send();
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
        $taxRate = (float) ($get('tax_rate') ?? 0);
        $isTaxInclusive = (bool) $get('is_tax_inclusive');

        $lineAmount = $quantity * $unitPrice;

        if ($isTaxInclusive) {
            $lineTotal = $lineAmount;
            $unitPriceExcTax = $taxRate > 0 ? $unitPrice / (1 + $taxRate / 100) : $unitPrice;
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
}
