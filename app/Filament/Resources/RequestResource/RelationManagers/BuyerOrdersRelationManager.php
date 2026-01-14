<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\BuyerQuoteStatus;
use App\Enums\OrderStatus;
use App\Enums\RequestStage;
use App\Filament\Actions\DownloadPdfAction;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\Request;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class BuyerOrdersRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'buyerOrders';

    protected static ?string $title = 'Buyer Orders';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-shopping-cart';

    protected static function getAssociatedStage(): RequestStage
    {
        return RequestStage::AWAITING_BUYER_CONFIRMATION;
    }

    protected static function getBaseTabTitle(): string
    {
        return 'Buyer Orders';
    }

    /**
     * Get the form schema for buyer orders.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public function getFormSchema(): array
    {
        return [
            Section::make('Order Details')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            Placeholder::make('order_number_display')
                                ->label('Order Number')
                                ->content(fn (?BuyerOrder $record): string => $record->order_number ?? 'Auto-generated'),
                            Placeholder::make('status_display')
                                ->label('Status')
                                ->content(fn (?BuyerOrder $record): string => $record?->status->getLabel() ?? 'Draft'),
                            Placeholder::make('buyer_quote_display')
                                ->label('Source Quote')
                                ->content(fn (?BuyerOrder $record): string => $record->buyerQuote->quote_number ?? 'None'),
                            Placeholder::make('currency_display')
                                ->label('Currency')
                                ->content(fn (?BuyerOrder $record): string => $record?->buyerQuote?->currency?->code ?? '-'),
                        ]),
                    Grid::make(4)
                        ->schema([
                            Placeholder::make('ordered_at_display')
                                ->label('Ordered At')
                                ->content(fn (?BuyerOrder $record): string => $record->ordered_at?->format('Y-m-d H:i') ?? '-'),
                            Placeholder::make('confirmed_at_display')
                                ->label('Confirmed At')
                                ->content(fn (?BuyerOrder $record): string => $record->confirmed_at?->format('Y-m-d H:i') ?? '-'),
                            Placeholder::make('buyer_display')
                                ->label('Buyer')
                                ->content(fn (?BuyerOrder $record): string => $record->buyer->name ?? '-'),
                            Placeholder::make('buyer_reference_display')
                                ->label('Buyer Reference')
                                ->content(fn (?BuyerOrder $record): string => $record->buyer_reference ?? '-'),
                        ]),
                ]),

            Section::make('Payment Terms')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Placeholder::make('prepayment_percent_display')
                                ->label('Prepayment %')
                                ->content(fn (?BuyerOrder $record): string => ($record->prepayment_percent ?? 0).'%'),
                            Placeholder::make('payment_terms_days_display')
                                ->label('Payment Terms (Days)')
                                ->content(fn (?BuyerOrder $record): string => (string) ($record->payment_terms_days ?? 30)),
                            Placeholder::make('payment_terms_text_display')
                                ->label('Payment Terms Description')
                                ->content(fn (?BuyerOrder $record): string => $record->payment_terms_text ?? '-'),
                        ]),
                ])
                ->collapsible(),

            Section::make('Line Items')
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Grid::make(12)
                                ->schema([
                                    TextInput::make('description')
                                        ->columnSpan(5)
                                        ->disabled(),
                                    TextInput::make('quantity')
                                        ->columnSpan(2)
                                        ->disabled(),
                                    TextInput::make('unit')
                                        ->columnSpan(1)
                                        ->disabled(),
                                    TextInput::make('unit_price')
                                        ->label('Price')
                                        ->columnSpan(2)
                                        ->disabled(),
                                    TextInput::make('line_total')
                                        ->label('Total')
                                        ->columnSpan(2)
                                        ->disabled(),
                                ]),
                        ])
                        ->columns(1)
                        ->deletable(false)
                        ->addable(false)
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['description'] ?? null),
                ]),

            Section::make('Summary')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            Placeholder::make('subtotal_display')
                                ->label('Subtotal')
                                ->content(fn (?BuyerOrder $record): string => $record instanceof BuyerOrder
                                    ? ($record->buyerQuote?->currency?->formatNumber((float) $record->subtotal) ?? number_format((float) $record->subtotal, 2))
                                    : '0,-'),
                            Placeholder::make('tax_total_display')
                                ->label('Tax Total')
                                ->content(fn (?BuyerOrder $record): string => $record instanceof BuyerOrder
                                    ? ($record->buyerQuote?->currency?->formatNumber((float) $record->tax_total) ?? number_format((float) $record->tax_total, 2))
                                    : '0,-'),
                            Placeholder::make('total_display')
                                ->label('Total')
                                ->content(fn (?BuyerOrder $record): string => $record instanceof BuyerOrder
                                    ? ($record->buyerQuote?->currency?->format((float) $record->total) ?? number_format((float) $record->total, 2))
                                    : '0,-'),
                            Placeholder::make('total_base_display')
                                ->label('Total (Base)')
                                ->content(function (?BuyerOrder $record): string {
                                    if (! $record instanceof BuyerOrder) {
                                        return '0,-';
                                    }
                                    /** @var \App\Models\Team|null $team */
                                    $team = \Filament\Facades\Filament::getTenant();
                                    $baseCurrency = $team?->getBaseCurrency();

                                    return $baseCurrency !== null
                                        ? $baseCurrency->format((float) $record->total)
                                        : number_format((float) $record->total, 2);
                                }),
                        ]),
                ])
                ->collapsible(),

            Section::make('Notes')
                ->schema([
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2)
                        ->disabled(),
                    Textarea::make('internal_notes')
                        ->label('Internal Notes')
                        ->rows(2)
                        ->disabled(),
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
                    ->formatStateUsing(fn (BuyerOrder $record): string => $record->buyerQuote?->currency?->formatNumber((float) $record->subtotal) ?? number_format((float) $record->subtotal, 2))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (BuyerOrder $record): string => $record->buyerQuote?->currency?->format((float) $record->total) ?? number_format((float) $record->total, 2))
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
                ViewAction::make()
                    ->modalWidth('7xl')
                    ->form(fn (): array => $this->getFormSchema()),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => false), // Disable bulk delete for orders
                ]),
            ]);
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Request $ownerRecord */
        $hasConfirmed = $ownerRecord->buyerOrders()
            ->whereNotIn('status', [OrderStatus::DRAFT, OrderStatus::CANCELLED])
            ->exists();

        return $hasConfirmed ? '✓' : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Request $ownerRecord */
        $hasConfirmed = $ownerRecord->buyerOrders()
            ->whereNotIn('status', [OrderStatus::DRAFT, OrderStatus::CANCELLED])
            ->exists();

        return $hasConfirmed ? 'success' : null;
    }
}
