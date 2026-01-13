<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\BuyerQuoteStatus;
use App\Enums\OrderStatus;
use App\Filament\Actions\DownloadPdfAction;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\Request;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class BuyerOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'buyerOrders';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-shopping-cart';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('order_number_display')
                                    ->label('Order Number')
                                    ->content(fn (?BuyerOrder $record): string => $record?->order_number ?? 'Auto-generated'),
                                Placeholder::make('status_display')
                                    ->label('Status')
                                    ->content(fn (?BuyerOrder $record): string => $record?->status->getLabel() ?? 'Draft'),
                                Placeholder::make('buyer_quote_display')
                                    ->label('Source Quote')
                                    ->content(fn (?BuyerOrder $record): string => $record?->buyerQuote?->quote_number ?? 'None'),
                            ]),
                    ]),

                Section::make('Payment Terms (Locked)')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('payment_terms_days_display')
                                    ->label('Payment Terms (Days)')
                                    ->content(fn (?BuyerOrder $record): string => (string) ($record?->payment_terms_days ?? 30)),
                                Placeholder::make('payment_terms_text_display')
                                    ->label('Payment Terms Description')
                                    ->content(fn (?BuyerOrder $record): string => $record?->payment_terms_text ?? '-'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Line Items (Locked)')
                    ->schema([
                        Placeholder::make('items_display')
                            ->label('')
                            ->content(function (?BuyerOrder $record): string {
                                if (! $record instanceof \App\Models\BuyerOrder) {
                                    return 'No items';
                                }

                                $items = $record->items;
                                if ($items->isEmpty()) {
                                    return 'No items';
                                }

                                $output = '';
                                foreach ($items as $item) {
                                    $output .= sprintf(
                                        "%s - Qty: %s %s @ %s = %s\n",
                                        $item->description,
                                        number_format((float) $item->quantity, 2),
                                        $item->unit,
                                        number_format((float) $item->unit_price, 2),
                                        number_format((float) $item->line_total, 2)
                                    );
                                }

                                return $output;
                            }),
                    ])
                    ->collapsible(),

                Section::make('Summary (Locked)')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(fn (?BuyerOrder $record): string => $record instanceof \App\Models\BuyerOrder
                                        ? number_format((float) $record->subtotal, 2)
                                        : '0.00'),
                                Placeholder::make('tax_total_display')
                                    ->label('Tax Total')
                                    ->content(fn (?BuyerOrder $record): string => $record instanceof \App\Models\BuyerOrder
                                        ? number_format((float) $record->tax_total, 2)
                                        : '0.00'),
                                Placeholder::make('total_display')
                                    ->label('Total')
                                    ->content(fn (?BuyerOrder $record): string => $record instanceof \App\Models\BuyerOrder
                                        ? number_format((float) $record->total, 2)
                                        : '0.00'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->disabled(fn (?BuyerOrder $record): bool => $record instanceof \App\Models\BuyerOrder && ! $record->status->canEdit()),
                        Textarea::make('internal_notes')
                            ->label('Internal Notes')
                            ->rows(2)
                            ->disabled(fn (?BuyerOrder $record): bool => $record instanceof \App\Models\BuyerOrder && ! $record->status->canEdit()),
                    ])
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('order_number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('buyerQuote.quote_number')
                    ->label('Source Quote')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->label('Total')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('payment_terms_days')
                    ->label('Terms')
                    ->suffix(' days')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
                TextColumn::make('ordered_at')
                    ->label('Ordered')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('confirmed_at')
                    ->label('Confirmed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::class),
            ])
            ->headerActions([
                Action::make('createFromQuote')
                    ->label('Create from Quote')
                    ->icon('heroicon-o-document-plus')
                    ->size(Size::Small)
                    ->color('primary')
                    ->form([
                        \Filament\Forms\Components\Select::make('buyer_quote_id')
                            ->label('Select Accepted Quote')
                            ->options(fn (): array => BuyerQuote::query()
                                ->where('request_id', $request->getKey())
                                ->where('status', BuyerQuoteStatus::ACCEPTED)
                                ->get()
                                ->mapWithKeys(fn (BuyerQuote $quote): array => [
                                    $quote->getKey() => sprintf(
                                        '%s (v%d) - %s',
                                        $quote->quote_number,
                                        $quote->version,
                                        number_format((float) $quote->total, 2)
                                    ),
                                ])
                                ->all())
                            ->required()
                            ->searchable()
                            ->helperText('Only accepted quotes can be converted to orders.'),
                    ])
                    ->action(function (array $data) use ($request): void {
                        /** @var BuyerQuote $buyerQuote */
                        $buyerQuote = BuyerQuote::findOrFail($data['buyer_quote_id']);

                        // Check if quote belongs to this request
                        if ($buyerQuote->request_id !== $request->getKey()) {
                            Notification::make()
                                ->title('Error')
                                ->body('Quote does not belong to this request.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Create order from quote
                        $order = BuyerOrder::createFromQuote($buyerQuote);

                        // Show credit limit warning if applicable
                        $warning = $order->getCreditLimitWarning();
                        if ($warning !== null) {
                            Notification::make()
                                ->title('Credit Limit Warning')
                                ->body($warning)
                                ->warning()
                                ->persistent()
                                ->send();
                        }

                        Notification::make()
                            ->title('Order created')
                            ->body(sprintf('Order %s created from quote %s.', $order->order_number, $buyerQuote->quote_number))
                            ->success()
                            ->send();
                    })
                    ->visible(
                        // Only show if there are accepted quotes

                        fn (): bool => BuyerQuote::query()
                            ->where('request_id', $request->getKey())
                            ->where('status', BuyerQuoteStatus::ACCEPTED)
                            ->exists()),
            ])
            ->recordActions([
                ViewAction::make(),
                DownloadPdfAction::make()
                    ->label('PDF'),
                Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BuyerOrder $record): bool => $record->status->canConfirm())
                    ->requiresConfirmation()
                    ->modalHeading('Confirm this order?')
                    ->modalDescription(function (BuyerOrder $record): string {
                        $warning = $record->getCreditLimitWarning();
                        $message = 'This will mark the order as confirmed.';
                        if ($warning !== null) {
                            $message .= "\n\n".$warning;
                        }

                        return $message;
                    })
                    ->action(function (BuyerOrder $record): void {
                        $record->confirm();
                        Notification::make()
                            ->title('Order confirmed')
                            ->success()
                            ->send();
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (BuyerOrder $record): bool => $record->status->canCancel())
                    ->requiresConfirmation()
                    ->modalHeading('Cancel this order?')
                    ->modalDescription('This will mark the order as cancelled. This action cannot be undone.')
                    ->action(function (BuyerOrder $record): void {
                        $record->cancel();
                        Notification::make()
                            ->title('Order cancelled')
                            ->warning()
                            ->send();
                    }),
                Action::make('progress')
                    ->label('Next Status')
                    ->icon('heroicon-o-arrow-right')
                    ->color('primary')
                    ->visible(fn (BuyerOrder $record): bool => $record->status->canProgress() && $record->status !== OrderStatus::DRAFT)
                    ->requiresConfirmation()
                    ->modalHeading(fn (BuyerOrder $record): string => sprintf(
                        'Progress to %s?',
                        $record->status->getNextStatus()?->getLabel() ?? 'Next'
                    ))
                    ->action(function (BuyerOrder $record): void {
                        $record->progressStatus();
                        Notification::make()
                            ->title('Order status updated')
                            ->body(sprintf('Order is now %s.', $record->status->getLabel()))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => false), // Disable bulk delete for orders
                ]),
            ]);
    }
}
