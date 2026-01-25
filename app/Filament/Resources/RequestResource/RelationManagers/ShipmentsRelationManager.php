<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\ItemCondition;
use App\Enums\OrderStatus;
use App\Enums\RequestStage;
use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\Request;
use App\Models\Shipment;
use App\Models\SupplierOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

final class ShipmentsRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'supplierOrders';

    protected static ?string $title = 'Inbound Shipments';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-truck';

    protected static function getAssociatedStage(): RequestStage
    {
        return RequestStage::AWAITING_SHIPMENT;
    }

    protected static function getBaseTabTitle(): string
    {
        return 'Inbound Shipments';
    }

    /**
     * Get the form schema for creating/editing a shipment.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function getShipmentFormSchema(SupplierOrder $supplierOrder): array
    {
        return [
            Section::make('Shipment Details')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Placeholder::make('supplier_display')
                                ->label('Supplier')
                                ->content($supplierOrder->supplier->name),
                            Placeholder::make('po_display')
                                ->label('PO Number')
                                ->content($supplierOrder->po_number),
                            Select::make('status')
                                ->options(ShipmentStatus::class)
                                ->default(ShipmentStatus::PENDING)
                                ->required(),
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
                ->description('Select items from this order to include in the shipment')
                ->schema([
                    Repeater::make('shipment_items')
                        ->schema([
                            Grid::make(12)
                                ->schema([
                                    Select::make('supplier_order_item_id')
                                        ->label('Order Item')
                                        ->options(
                                            $supplierOrder->items()
                                                ->get()
                                                ->mapWithKeys(fn ($item): array => [
                                                    $item->getKey() => sprintf(
                                                        '%s (%s %s)',
                                                        $item->description,
                                                        $item->quantity,
                                                        $item->unit?->value ?? 'N/A'
                                                    ),
                                                ])
                                                ->all()
                                        )
                                        ->required()
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, ?int $state) use ($supplierOrder): void {
                                            if ($state === null) {
                                                return;
                                            }
                                            $orderItem = $supplierOrder->items()->find($state);
                                            if ($orderItem !== null) {
                                                // Prefill quantity_shipped with the order item quantity
                                                $set('quantity_shipped', (string) $orderItem->quantity);
                                            }
                                        })
                                        ->columnSpan(6),
                                    TextInput::make('quantity_shipped')
                                        ->label('Qty Shipped')
                                        ->numeric()
                                        ->required()
                                        ->step(0.0001)
                                        ->columnSpan(3),
                                    Select::make('condition')
                                        ->options(ItemCondition::class)
                                        ->default(ItemCondition::GOOD)
                                        ->required()
                                        ->columnSpan(3),
                                ]),
                        ])
                        ->columns(1)
                        ->defaultItems(0)
                        ->addActionLabel('Add Item')
                        ->reorderable(false),
                ]),

            Section::make('Notes')
                ->schema([
                    Textarea::make('notes')
                        ->rows(3),
                ])
                ->collapsed(),
        ];
    }

    /**
     * Get the form schema for viewing shipments of a supplier order.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function getViewShipmentsSchema(SupplierOrder $supplierOrder): array
    {
        $shipments = $supplierOrder->shipments()->with('items')->get();

        if ($shipments->isEmpty()) {
            return [
                Placeholder::make('no_shipments')
                    ->label('')
                    ->content(new HtmlString('<div class="text-gray-500 text-center py-8">No shipments created yet for this order.</div>')),
            ];
        }

        $sections = [];
        foreach ($shipments as $shipment) {
            $sections[] = Section::make($shipment->shipment_number)
                ->description(sprintf('%s • %s', $shipment->status->getLabel(), $shipment->carrier_name ?? 'No carrier'))
                ->icon($this->getShipmentStatusIcon($shipment->status))
                ->iconColor($this->getShipmentStatusColor($shipment->status))
                ->schema([
                    Grid::make(4)
                        ->schema([
                            Placeholder::make("tracking_{$shipment->id}")
                                ->label('Tracking')
                                ->content($shipment->tracking_number ?? '-'),
                            Placeholder::make("shipped_{$shipment->id}")
                                ->label('Shipped')
                                ->content($shipment->shipped_at?->format('Y-m-d H:i') ?? '-'),
                            Placeholder::make("expected_{$shipment->id}")
                                ->label('Expected')
                                ->content($shipment->expected_delivery_at?->format('Y-m-d H:i') ?? '-'),
                            Placeholder::make("delivered_{$shipment->id}")
                                ->label('Delivered')
                                ->content($shipment->delivered_at?->format('Y-m-d H:i') ?? '-'),
                        ]),
                    Placeholder::make("items_{$shipment->id}")
                        ->label('Items')
                        ->content(new HtmlString($this->formatShipmentItems($shipment))),
                ])
                ->collapsible()
                ->collapsed($shipment->status === ShipmentStatus::DELIVERED);
        }

        return $sections;
    }

    /**
     * Format shipment items as HTML table.
     */
    private function formatShipmentItems(Shipment $shipment): string
    {
        if ($shipment->items->isEmpty()) {
            return '<span class="text-gray-400">No items</span>';
        }

        $rows = $shipment->items->map(function ($item): string {
            $orderItem = $item->supplierOrderItem;
            $description = $orderItem !== null ? $orderItem->description : 'Unknown item';
            $qtyShipped = number_format((float) $item->quantity_shipped, 2);
            $qtyReceived = $item->quantity_received !== null ? number_format((float) $item->quantity_received, 2) : '-';
            $condition = $item->condition->getLabel();

            return "<tr><td class='pr-4'>{$description}</td><td class='pr-4 text-right'>{$qtyShipped}</td><td class='pr-4 text-right'>{$qtyReceived}</td><td>{$condition}</td></tr>";
        })->join('');

        return "<table class='text-sm w-full'><thead><tr class='text-gray-500'><th class='text-left pr-4'>Item</th><th class='text-right pr-4'>Shipped</th><th class='text-right pr-4'>Received</th><th class='text-left'>Condition</th></tr></thead><tbody>{$rows}</tbody></table>";
    }

    /**
     * Get icon for shipment status.
     */
    private function getShipmentStatusIcon(ShipmentStatus $status): string
    {
        return match ($status) {
            ShipmentStatus::PENDING => 'heroicon-o-clock',
            ShipmentStatus::IN_TRANSIT => 'heroicon-o-truck',
            ShipmentStatus::DELIVERED => 'heroicon-o-check-circle',
            ShipmentStatus::PARTIAL => 'heroicon-o-exclamation-triangle',
            ShipmentStatus::FAILED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Get color for shipment status.
     */
    private function getShipmentStatusColor(ShipmentStatus $status): string
    {
        return match ($status) {
            ShipmentStatus::PENDING => 'gray',
            ShipmentStatus::IN_TRANSIT => 'info',
            ShipmentStatus::DELIVERED => 'success',
            ShipmentStatus::PARTIAL => 'warning',
            ShipmentStatus::FAILED => 'danger',
        };
    }

    /**
     * Get the overall shipment status for a supplier order.
     */
    private function getOrderShipmentStatus(SupplierOrder $order): string
    {
        $shipments = $order->shipments;

        if ($shipments->isEmpty()) {
            return 'pending';
        }

        $allDelivered = $shipments->every(fn (Shipment $s): bool => $s->status === ShipmentStatus::DELIVERED);
        if ($allDelivered) {
            return 'delivered';
        }

        $anyInTransit = $shipments->contains(fn (Shipment $s): bool => $s->status === ShipmentStatus::IN_TRANSIT);
        if ($anyInTransit) {
            return 'in_transit';
        }

        $anyPartial = $shipments->contains(fn (Shipment $s): bool => $s->status === ShipmentStatus::PARTIAL);
        if ($anyPartial) {
            return 'partial';
        }

        return 'pending';
    }

    /**
     * Get badge color for order shipment status.
     */
    private function getOrderShipmentStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'gray',
            'in_transit' => 'info',
            'delivered' => 'success',
            'partial' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Get label for order shipment status.
     */
    private function getOrderShipmentStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Awaiting Shipment',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'partial' => 'Partial',
            default => 'Unknown',
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('po_number')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query
                ->whereIn('status', [OrderStatus::CONFIRMED, OrderStatus::COMPLETED])
                ->with(['supplier', 'shipments', 'items'])
            )
            ->columns([
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('po_number')
                    ->label('PO #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Order Total')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('shipments_count')
                    ->label('Shipments')
                    ->counts('shipments')
                    ->sortable(),
                TextColumn::make('shipment_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (SupplierOrder $record): string => $this->getOrderShipmentStatusLabel($this->getOrderShipmentStatus($record)))
                    ->color(fn (SupplierOrder $record): string => $this->getOrderShipmentStatusColor($this->getOrderShipmentStatus($record))),
                TextColumn::make('expected_delivery_date')
                    ->label('Expected')
                    ->date()
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('create_shipment')
                    ->label('Create Shipment')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->size(Size::Small)
                    ->modalHeading(fn (SupplierOrder $record): string => "Create Shipment for {$record->supplier->name}")
                    ->modalWidth('4xl')
                    ->form(fn (SupplierOrder $record): array => $this->getShipmentFormSchema($record))
                    ->fillForm(function (SupplierOrder $record): array {
                        // Prefill shipment items with all items from the supplier order
                        $shipmentItems = $record->items()
                            ->orderBy('sort_order')
                            ->get()
                            ->map(fn ($item): array => [
                                'supplier_order_item_id' => $item->getKey(),
                                'quantity_shipped' => (string) $item->quantity,
                                'condition' => ItemCondition::GOOD->value,
                            ])
                            ->toArray();

                        return [
                            'status' => ShipmentStatus::PENDING->value,
                            'shipment_items' => $shipmentItems,
                        ];
                    })
                    ->action(function (SupplierOrder $record, array $data): void {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();

                        // Create the shipment
                        $shipment = new Shipment;
                        $shipment->team_id = $request->team_id;
                        $shipment->creator_id = auth()->id() !== null ? (int) auth()->id() : null;
                        $shipment->request_id = $request->getKey();
                        $shipment->type = ShipmentType::INBOUND;
                        $shipment->supplier_order_id = $record->getKey();
                        $shipment->status = $data['status'];
                        $shipment->carrier_name = $data['carrier_name'] ?? null;
                        $shipment->tracking_number = $data['tracking_number'] ?? null;
                        $shipment->shipped_at = $data['shipped_at'] ?? null;
                        $shipment->expected_delivery_at = $data['expected_delivery_at'] ?? null;
                        $shipment->delivered_at = $data['delivered_at'] ?? null;
                        $shipment->notes = $data['notes'] ?? null;
                        $shipment->save();

                        // Create shipment items
                        $sortOrder = 0;
                        foreach ($data['shipment_items'] ?? [] as $itemData) {
                            if (empty($itemData['supplier_order_item_id'])) {
                                continue;
                            }

                            $shipment->items()->create([
                                'supplier_order_item_id' => $itemData['supplier_order_item_id'],
                                'quantity_shipped' => $itemData['quantity_shipped'],
                                'condition' => $itemData['condition'],
                                'sort_order' => $sortOrder++,
                            ]);
                        }

                        Notification::make()
                            ->title('Shipment created')
                            ->body("Shipment {$shipment->shipment_number} created successfully.")
                            ->success()
                            ->send();
                    }),
                Action::make('view_shipments')
                    ->label('View Shipments')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->size(Size::Small)
                    ->visible(fn (SupplierOrder $record): bool => $record->shipments()->exists())
                    ->modalHeading(fn (SupplierOrder $record): string => "Shipments for {$record->supplier->name} ({$record->po_number})")
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->form(fn (SupplierOrder $record): array => $this->getViewShipmentsSchema($record)),
                Action::make('mark_in_transit')
                    ->label('Ship')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->size(Size::Small)
                    ->visible(fn (SupplierOrder $record): bool => $record->shipments()
                        ->where('status', ShipmentStatus::PENDING)
                        ->exists())
                    ->form([
                        Select::make('shipment_id')
                            ->label('Select Shipment')
                            ->options(fn (SupplierOrder $record): array => $record->shipments()
                                ->where('status', ShipmentStatus::PENDING)
                                ->get()
                                ->mapWithKeys(fn (Shipment $s): array => [
                                    $s->getKey() => $s->shipment_number,
                                ])
                                ->all())
                            ->required(),
                        TextInput::make('tracking_number')
                            ->label('Tracking Number'),
                        DateTimePicker::make('expected_delivery_at')
                            ->label('Expected Delivery'),
                    ])
                    ->action(function (array $data): void {
                        /** @var Shipment $shipment */
                        $shipment = Shipment::findOrFail($data['shipment_id']);
                        $shipment->markAsInTransit(
                            $data['tracking_number'] ?? null,
                            $data['expected_delivery_at'] !== null ? \Illuminate\Support\Carbon::parse($data['expected_delivery_at']) : null
                        );

                        Notification::make()
                            ->title('Shipment in transit')
                            ->success()
                            ->send();
                    }),
                Action::make('mark_delivered')
                    ->label('Deliver')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->size(Size::Small)
                    ->visible(fn (SupplierOrder $record): bool => $record->shipments()
                        ->whereIn('status', [ShipmentStatus::IN_TRANSIT, ShipmentStatus::PARTIAL])
                        ->exists())
                    ->form([
                        Select::make('shipment_id')
                            ->label('Select Shipment')
                            ->options(fn (SupplierOrder $record): array => $record->shipments()
                                ->whereIn('status', [ShipmentStatus::IN_TRANSIT, ShipmentStatus::PARTIAL])
                                ->get()
                                ->mapWithKeys(fn (Shipment $s): array => [
                                    $s->getKey() => $s->shipment_number,
                                ])
                                ->all())
                            ->required(),
                        DateTimePicker::make('delivered_at')
                            ->label('Delivered At')
                            ->default(now()),
                    ])
                    ->action(function (array $data): void {
                        /** @var Shipment $shipment */
                        $shipment = Shipment::findOrFail($data['shipment_id']);
                        $shipment->markAsDelivered(
                            $data['delivered_at'] !== null ? \Illuminate\Support\Carbon::parse($data['delivered_at']) : null
                        );

                        Notification::make()
                            ->title('Shipment delivered')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No confirmed supplier orders')
            ->emptyStateDescription('Supplier orders will appear here once confirmed. Create shipments to track deliveries.')
            ->emptyStateIcon('heroicon-o-truck');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Request $ownerRecord */
        $hasDelivered = $ownerRecord->shipments()
            ->where('status', ShipmentStatus::DELIVERED)
            ->exists();

        return $hasDelivered ? '✓' : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Request $ownerRecord */
        $hasDelivered = $ownerRecord->shipments()
            ->where('status', ShipmentStatus::DELIVERED)
            ->exists();

        return $hasDelivered ? 'success' : null;
    }
}
