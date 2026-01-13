<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\ItemCondition;
use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Models\BuyerOrder;
use App\Models\Request;
use App\Models\Shipment;
use App\Models\SupplierOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
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

final class ShipmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'shipments';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-truck';

    public function form(Schema $schema): Schema
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $schema
            ->components([
                Section::make('Shipment Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('type')
                                    ->label('Shipment Type')
                                    ->options(ShipmentType::class)
                                    ->default(ShipmentType::INBOUND)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        // Clear opposite order reference when type changes
                                        if ($state === ShipmentType::INBOUND->value) {
                                            $set('buyer_order_id', null);
                                        } else {
                                            $set('supplier_order_id', null);
                                        }
                                    }),
                                Select::make('status')
                                    ->options(ShipmentStatus::class)
                                    ->default(ShipmentStatus::PENDING)
                                    ->required(),
                                Placeholder::make('shipment_number_display')
                                    ->label('Shipment Number')
                                    ->content(fn (?Shipment $record): string => $record?->shipment_number ?? 'Auto-generated'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Select::make('supplier_order_id')
                                    ->label('Supplier Order')
                                    ->options(fn (): array => SupplierOrder::query()
                                        ->where('team_id', $request->team_id)
                                        ->where('request_id', $request->getKey())
                                        ->get()
                                        ->mapWithKeys(fn (SupplierOrder $order): array => [
                                            $order->getKey() => "[{$order->order_number}] {$order->supplier->name}",
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->visible(fn (Get $get): bool => $get('type') === ShipmentType::INBOUND->value)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                                        if ($state === null) {
                                            return;
                                        }
                                        // Could pre-populate items from order
                                    }),
                                Select::make('buyer_order_id')
                                    ->label('Buyer Order')
                                    ->options(fn (): array => BuyerOrder::query()
                                        ->where('team_id', $request->team_id)
                                        ->where('request_id', $request->getKey())
                                        ->get()
                                        ->mapWithKeys(fn (BuyerOrder $order): array => [
                                            $order->getKey() => "[{$order->order_number}] {$order->buyer->name}",
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->visible(fn (Get $get): bool => $get('type') === ShipmentType::OUTBOUND->value)
                                    ->live(),
                            ]),
                    ]),

                Section::make('Carrier Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('carrier_name')
                                    ->label('Carrier Name')
                                    ->maxLength(255)
                                    ->placeholder('e.g., DHL, FedEx, UPS'),
                                TextInput::make('tracking_number')
                                    ->label('Tracking Number')
                                    ->maxLength(255),
                            ]),
                        Grid::make(3)
                            ->schema([
                                DateTimePicker::make('shipped_at')
                                    ->label('Shipped At'),
                                DateTimePicker::make('expected_delivery_at')
                                    ->label('Expected Delivery'),
                                DateTimePicker::make('delivered_at')
                                    ->label('Delivered At'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Shipment Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        Select::make('supplier_order_item_id')
                                            ->label('Order Item (Inbound)')
                                            ->options(function (Get $get): array {
                                                $supplierOrderId = $get('../../supplier_order_id');
                                                if ($supplierOrderId === null) {
                                                    return [];
                                                }

                                                $order = SupplierOrder::find($supplierOrderId);
                                                if ($order === null) {
                                                    return [];
                                                }

                                                return $order->items()
                                                    ->get()
                                                    ->mapWithKeys(fn ($item): array => [
                                                        $item->getKey() => sprintf('%s (%s %s)', $item->description, $item->quantity, $item->unit),
                                                    ])
                                                    ->all();
                                            })
                                            ->searchable()
                                            ->columnSpan(6)
                                            ->visible(fn (Get $get): bool => $get('../../type') === ShipmentType::INBOUND->value),
                                        Select::make('buyer_order_item_id')
                                            ->label('Order Item (Outbound)')
                                            ->options(function (Get $get): array {
                                                $buyerOrderId = $get('../../buyer_order_id');
                                                if ($buyerOrderId === null) {
                                                    return [];
                                                }

                                                $order = BuyerOrder::find($buyerOrderId);
                                                if ($order === null) {
                                                    return [];
                                                }

                                                return $order->items()
                                                    ->get()
                                                    ->mapWithKeys(fn ($item): array => [
                                                        $item->getKey() => sprintf('%s (%s %s)', $item->description, $item->quantity, $item->unit),
                                                    ])
                                                    ->all();
                                            })
                                            ->searchable()
                                            ->columnSpan(6)
                                            ->visible(fn (Get $get): bool => $get('../../type') === ShipmentType::OUTBOUND->value),
                                        TextInput::make('quantity_shipped')
                                            ->label('Qty Shipped')
                                            ->numeric()
                                            ->required()
                                            ->step(0.0001)
                                            ->columnSpan(3),
                                        TextInput::make('quantity_received')
                                            ->label('Qty Received')
                                            ->numeric()
                                            ->step(0.0001)
                                            ->columnSpan(3)
                                            ->helperText('Fill on delivery'),
                                    ]),
                                Grid::make(12)
                                    ->schema([
                                        Select::make('condition')
                                            ->options(ItemCondition::class)
                                            ->default(ItemCondition::GOOD)
                                            ->required()
                                            ->columnSpan(4)
                                            ->live(),
                                        TextInput::make('condition_notes')
                                            ->label('Condition Notes')
                                            ->columnSpan(8)
                                            ->visible(fn (Get $get): bool => $get('condition') !== ItemCondition::GOOD->value),
                                    ]),
                            ])
                            ->columns(1)
                            ->defaultItems(0)
                            ->addActionLabel('Add Item')
                            ->reorderable('sort_order')
                            ->collapsible(),
                    ]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3),
                    ])
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('shipment_number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('shipment_number')
                    ->label('Shipment #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('supplierOrder.supplier.name')
                    ->label('Supplier')
                    ->visible(fn (): bool => true)
                    ->placeholder('-'),
                TextColumn::make('buyerOrder.buyer.name')
                    ->label('Buyer')
                    ->visible(fn (): bool => true)
                    ->placeholder('-'),
                TextColumn::make('carrier_name')
                    ->label('Carrier')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('tracking_number')
                    ->label('Tracking')
                    ->searchable()
                    ->toggleable()
                    ->copyable(),
                TextColumn::make('shipped_at')
                    ->label('Shipped')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expected_delivery_at')
                    ->label('Expected')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivered_at')
                    ->label('Delivered')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(ShipmentType::class),
                SelectFilter::make('status')
                    ->options(ShipmentStatus::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('mark_in_transit')
                    ->label('Ship')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (Shipment $record): bool => $record->status === ShipmentStatus::PENDING)
                    ->form([
                        TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->default(fn (Shipment $record): ?string => $record->tracking_number),
                        DateTimePicker::make('expected_delivery_at')
                            ->label('Expected Delivery')
                            ->default(fn (Shipment $record): ?\Illuminate\Support\Carbon => $record->expected_delivery_at),
                    ])
                    ->action(function (Shipment $record, array $data): void {
                        $record->markAsInTransit(
                            $data['tracking_number'] ?? null,
                            $data['expected_delivery_at'] ? \Illuminate\Support\Carbon::parse($data['expected_delivery_at']) : null
                        );
                        Notification::make()
                            ->title('Shipment marked as in transit')
                            ->success()
                            ->send();
                    }),
                Action::make('mark_delivered')
                    ->label('Deliver')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Shipment $record): bool => in_array($record->status, [ShipmentStatus::IN_TRANSIT, ShipmentStatus::PARTIAL], true))
                    ->form([
                        DateTimePicker::make('delivered_at')
                            ->label('Delivered At')
                            ->default(now()),
                    ])
                    ->action(function (Shipment $record, array $data): void {
                        $record->markAsDelivered(
                            $data['delivered_at'] ? \Illuminate\Support\Carbon::parse($data['delivered_at']) : null
                        );
                        Notification::make()
                            ->title('Shipment marked as delivered')
                            ->success()
                            ->send();
                    }),
                Action::make('mark_failed')
                    ->label('Failed')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Shipment $record): bool => ! $record->status->isTerminal())
                    ->form([
                        Textarea::make('reason')
                            ->label('Failure Reason')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Shipment $record, array $data): void {
                        $record->markAsFailed($data['reason'] ?? null);
                        Notification::make()
                            ->title('Shipment marked as failed')
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
}
