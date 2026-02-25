<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\ItemCondition;
use App\Enums\OrderStatus;
use App\Enums\RequestStage;
use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Filament\Actions\DownloadPdfAction;
use App\Filament\Resources\PeopleResource;
use App\Filament\Resources\RequestResource;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Mail\Erp\ShipmentToBuyerMail;
use App\Models\PaymentDocumentApproval;
use App\Models\People;
use App\Models\Request;
use App\Models\Shipment;
use App\Models\SupplierOrder;
use Filament\Facades\Filament;
use App\Services\Email\EmailTemplateService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Support\Facades\Log;
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
use Filament\Schemas\Components\Tabs\Tab;
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

    public function mount(): void
    {
        parent::mount();

        /** @var Request $request */
        $request = $this->getOwnerRecord();
        if (static::hasUnapprovedGoodsReceiveDocuments($request)) {
            Notification::make()
                ->title('Access Restricted')
                ->body('All Goods Receive documents must be approved before you can access Inbound Shipments.')
                ->warning()
                ->send();

            $this->redirect(RequestResource::getUrl('view', ['record' => $request->id, 'activeRelationManager' => 'goodsReceive']));
        }
    }

    /**
     * @return Tab
     */
    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        $tab = parent::getTabComponent($ownerRecord, $pageClass);

        /** @var Request $ownerRecord */
        if (static::hasUnapprovedGoodsReceiveDocuments($ownerRecord)) {
            $tab->disabled()
                ->badgeColor('gray')
                ->badgeTooltip('All Goods Receive documents must be approved first')
                ->extraAttributes([
                    'class' => 'goods-receive-disabled-tab',
                ]);
        }

        return $tab;
    }

    /**
     * Check if the request has any goods receive documents that are not yet approved.
     */
    private static function hasUnapprovedGoodsReceiveDocuments(Request $request): bool
    {
        $media = $request->getMedia('goods_receive');
        if ($media->isEmpty() || $request->team_id === null) {
            return false;
        }

        $approvedMediaIds = PaymentDocumentApproval::query()
            ->where('team_id', $request->team_id)
            ->whereIn('media_id', $media->pluck('id')->toArray())
            ->pluck('media_id')
            ->toArray();

        return $media->contains(fn ($m) => ! in_array($m->id, $approvedMediaIds, true));
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
                                ->required()
                                ->selectablePlaceholder(false),
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
                                                ->filter(fn ($item): bool => $item->getRemainingQuantity() > 0)
                                                ->mapWithKeys(fn ($item): array => [
                                                    $item->getKey() => $item->description,
                                                ])
                                                ->all()
                                        )
                                        ->required()
                                        
                                        ->selectablePlaceholder(false)
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, ?int $state) use ($supplierOrder): void {
                                            if ($state === null) {
                                                return;
                                            }
                                            $orderItem = $supplierOrder->items()->find($state);
                                            if ($orderItem !== null) {
                                                // Prefill quantity_shipped with the remaining quantity
                                                $remainingQty = $orderItem->getRemainingQuantity();
                                                $set('quantity_shipped', (string) $remainingQty);
                                            }
                                        })
                                        ->columnSpan(6),
                                    TextInput::make('quantity_shipped')
                                        ->label('Qty Shipped')
                                        ->numeric()
                                        ->required()
                                        ->step(0.0001)
                                        ->helperText(function ($get) use ($supplierOrder): ?string {
                                            $orderItemId = $get('supplier_order_item_id');
                                            if ($orderItemId === null) {
                                                return null;
                                            }
                                            $orderItem = $supplierOrder->items()->find($orderItemId);
                                            if ($orderItem === null) {
                                                return null;
                                            }
                                            $totalShipped = $orderItem->getTotalShippedQuantity();
                                            $orderedQty = (float) $orderItem->quantity;
                                            $remainingQty = $orderItem->getRemainingQuantity();
                                            
                                            if ($totalShipped > 0) {
                                                return sprintf(
                                                    '%s out of %s shipped. %s remaining.',
                                                    number_format($totalShipped, 0),
                                                    number_format($orderedQty, 0),
                                                    number_format($remainingQty, 0)
                                                );
                                            }
                                            
                                            return sprintf('Total ordered: %s', number_format($orderedQty, 0));
                                        })
                                        ->columnSpan(3),
                                    Select::make('condition')
                                        ->options(ItemCondition::class)
                                        ->default(ItemCondition::GOOD)
                                        ->required()
                                        ->selectablePlaceholder(false)
                                        ->columnSpan(3),
                                ]),
                        ])
                        ->columns(1)
                        ->defaultItems(0)
                        ->addActionLabel('Add Item')
                        ->reorderable(false),
                ]),

            Section::make('Additional Info')
                ->schema([
                    Select::make('pic_contact_id')
                        ->label('PIC Contact')
                        ->options(function () use ($supplierOrder): array {
                            $buyer = $supplierOrder->request->buyer ?? null;
                            if ($buyer === null) {
                                return [];
                            }
                            return $buyer->people()
                                ->select('people.id', 'people.name')
                                ->orderBy('people.name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->preload()
                        ->nullable()
                        ->helperText('Person in charge at the buyer for this shipment')
                        ->actionSchemaModel(People::class)
                        ->createOptionForm(PeopleResource::getFormSchema(excludeCompaniesField: false, excludeCustomFields: true))
                        ->createOptionUsing(function (array $data) use ($supplierOrder): int {
                            /** @var \App\Models\Team $team */
                            $team = Filament::getTenant();
                            $companyIds = $data['companies'] ?? [];
                            unset($data['companies']);
                            /** @var People $person */
                            $person = People::create([
                                ...$data,
                                'team_id' => $team->id,
                                'creator_id' => auth()->id(),
                            ]);
                            if (! empty($companyIds)) {
                                $person->companies()->sync($companyIds);
                            }
                            $buyer = $supplierOrder->request->buyer;
                            if ($buyer !== null) {
                                $buyer->people()->syncWithoutDetaching([$person->id]);
                            }
                            return $person->id;
                        }),
                    Textarea::make('notes')
                        ->rows(3),
                ]),
        ];
    }

    /**
     * Get the form schema for viewing shipments of a supplier order.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function getViewShipmentsSchema(SupplierOrder $supplierOrder): array
    {
        $shipments = $supplierOrder->shipments()
            ->with(['items', 'request.buyer', 'picContact'])
            ->get();

        if ($shipments->isEmpty()) {
            return [
                Placeholder::make('no_shipments')
                    ->label('')
                    ->content(new HtmlString('<div class="text-gray-500 text-center py-8">No shipments created yet for this order.</div>')),
            ];
        }

        $sections = [];
        foreach ($shipments as $shipment) {
            // Capture shipment ID and status for use in closures
            $shipmentId = $shipment->id;
            $shipmentType = $shipment->type;
            $shipmentStatus = $shipment->status;
            
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
                    Placeholder::make("pic_{$shipment->id}")
                        ->label('PIC Contact')
                        ->content($shipment->picContact?->name ?? '-'),
                    Placeholder::make("items_{$shipment->id}")
                        ->label('Items')
                        ->content(new HtmlString($this->formatShipmentItems($shipment))),
                    Placeholder::make("actions_{$shipment->id}")
                        ->label('Actions')
                        ->content(view('filament.components.shipment-actions', ['shipment' => $shipment]))
                        ->visible(fn () => $shipmentType === ShipmentType::INBOUND),
                ])
                ->collapsible()
                ->collapsed($shipmentStatus === ShipmentStatus::DELIVERED);
        }

        return $sections;
    }
    /**
     * Send or resend delivery order email to buyer.
     */
    public function sendDeliveryOrder(int $shipmentId): void
    {
        $shipment = Shipment::find($shipmentId);
        
        if (!$shipment) {
            Notification::make()
                ->title('Shipment not found')
                ->danger()
                ->send();
            return;
        }

        // Verify shipment belongs to current team
        /** @var \App\Models\Team $team */
        $team = \Filament\Facades\Filament::getTenant();
        if ($shipment->team_id !== $team->id) {
            Notification::make()
                ->title('Unauthorized')
                ->body('You do not have access to this shipment.')
                ->danger()
                ->send();
            return;
        }

        // Verify shipment is inbound and in transit
        if ($shipment->type !== ShipmentType::INBOUND) {
            Notification::make()
                ->title('Invalid shipment type')
                ->body('Only inbound shipments can send delivery orders.')
                ->warning()
                ->send();
            return;
        }

        if ($shipment->status !== ShipmentStatus::IN_TRANSIT) {
            Notification::make()
                ->title('Invalid shipment status')
                ->body('Delivery order can only be sent for shipments in transit.')
                ->warning()
                ->send();
            return;
        }

        // Get buyer email - for inbound shipments, buyer is accessed via request->buyer
        $buyer = $shipment->request?->buyer ?? null;
        $buyerEmail = $buyer?->email ?? null;
        $buyerName = $buyer?->name ?? 'Buyer';

        if (empty($buyerEmail)) {
            Notification::make()
                ->title('Email not sent')
                ->body("Delivery order email was not sent because the buyer ({$buyerName}) does not have an email address configured.")
                ->warning()
                ->send();
            return;
        }

        $isResend = $shipment->do_sent_at !== null;

        try {
            $emailService = app(EmailTemplateService::class);
            $settings = $shipment->team->getErpSettings();
            $emailService->sendWithTeamSettings(
                $shipment->team,
                new ShipmentToBuyerMail($shipment),
                $buyerEmail,
                $settings->email_template_delivery_order, // Old system fallback
                $settings->email_template_delivery_order_id ?? null, // New system
                \App\Models\EmailTemplate::TYPE_DELIVERY_ORDER
            );

            // Update do_sent_at timestamp
            $shipment->do_sent_at = now();
            $shipment->save();

            $actionText = $isResend ? 'resent' : 'sent';
            Notification::make()
                ->title('Delivery order '.$actionText)
                ->body("Delivery order email has been {$actionText} successfully to {$buyerEmail}.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Failed to send delivery order email', [
                'shipment_id' => $shipment->id,
                'buyer_email' => $buyerEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $actionText = $isResend ? 'resent' : 'sent';
            Notification::make()
                ->title('Failed to '.$actionText.' email')
                ->body("The email could not be {$actionText} to {$buyerEmail}. Error: ".$e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Mark a shipment as in transit (Ship action).
     */
    public function shipShipment(int $shipmentId): void
    {
        $shipment = Shipment::find($shipmentId);
        
        if (!$shipment) {
            Notification::make()
                ->title('Shipment not found')
                ->danger()
                ->send();
            return;
        }

        // Verify shipment belongs to current team
        /** @var \App\Models\Team $team */
        $team = \Filament\Facades\Filament::getTenant();
        if ($shipment->team_id !== $team->id) {
            Notification::make()
                ->title('Unauthorized')
                ->body('You do not have access to this shipment.')
                ->danger()
                ->send();
            return;
        }

        // Verify shipment is pending
        if ($shipment->status !== ShipmentStatus::PENDING) {
            Notification::make()
                ->title('Invalid shipment status')
                ->body('Only pending shipments can be marked as shipped.')
                ->warning()
                ->send();
            return;
        }

        try {
            $shipment->markAsInTransit();
            
            Notification::make()
                ->title('Shipment marked as shipped')
                ->body("Shipment {$shipment->shipment_number} has been marked as in transit.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to ship')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Mark a shipment as delivered (Deliver action).
     */
    public function deliverShipment(int $shipmentId): void
    {
        $shipment = Shipment::find($shipmentId);
        
        if (!$shipment) {
            Notification::make()
                ->title('Shipment not found')
                ->danger()
                ->send();
            return;
        }

        // Verify shipment belongs to current team
        /** @var \App\Models\Team $team */
        $team = \Filament\Facades\Filament::getTenant();
        if ($shipment->team_id !== $team->id) {
            Notification::make()
                ->title('Unauthorized')
                ->body('You do not have access to this shipment.')
                ->danger()
                ->send();
            return;
        }

        // Verify shipment is in transit or partial
        if (!in_array($shipment->status, [ShipmentStatus::IN_TRANSIT, ShipmentStatus::PARTIAL])) {
            Notification::make()
                ->title('Invalid shipment status')
                ->body('Only shipments in transit or partial can be marked as delivered.')
                ->warning()
                ->send();
            return;
        }

        try {
            $shipment->markAsDelivered();
            
            Notification::make()
                ->title('Shipment delivered')
                ->body("Shipment {$shipment->shipment_number} has been marked as delivered.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to deliver')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
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
            $qtyShipped = (float) $item->quantity_shipped;
            $qtyReceived = $item->quantity_received !== null ? (float) $item->quantity_received : null;
            $condition = $item->condition->getLabel();

            // Calculate total ordered quantity
            $orderedQty = $orderItem !== null ? (float) $orderItem->quantity : 0;

            // Always format shipped quantity as "X out of Y" when order item exists
            $shippedDisplay = number_format($qtyShipped, 2);
            if ($orderItem !== null && $orderedQty > 0) {
                $shippedDisplay = sprintf('%s out of %s', number_format($qtyShipped, 0), number_format($orderedQty, 0));
            }

            $receivedDisplay = $qtyReceived !== null ? number_format($qtyReceived, 2) : '-';

            return "<tr><td class='pr-4'>{$description}</td><td class='pr-4 text-right'>{$shippedDisplay}</td><td class='pr-4 text-right'>{$receivedDisplay}</td><td>{$condition}</td></tr>";
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
                ->whereIn('status', [OrderStatus::SENT, OrderStatus::COMPLETED])
                ->with(['supplier', 'shipments', 'items'])
            )
            ->columns([
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    
                    ->sortable(),
                TextColumn::make('po_number')
                    ->label('PO #')
                    
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
                ActionGroup::make([
                    Action::make('create_shipment')
                        ->label('Create Shipment')
                        ->icon('heroicon-o-plus')
                        ->color('primary')
                        ->size(Size::Small)
                        ->modalHeading(fn (SupplierOrder $record): string => "Create Shipment for {$record->supplier->name}")
                        ->modalWidth('4xl')
                        ->form(fn (SupplierOrder $record): array => $this->getShipmentFormSchema($record))
                        ->fillForm(function (SupplierOrder $record): array {
                            // Prefill shipment items with items that have remaining quantity
                            $shipmentItems = $record->items()
                                ->orderBy('sort_order')
                                ->get()
                                ->filter(fn ($item): bool => $item->getRemainingQuantity() > 0)
                                ->map(fn ($item): array => [
                                    'supplier_order_item_id' => $item->getKey(),
                                    'quantity_shipped' => (string) $item->getRemainingQuantity(),
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
                            $shipment->pic_contact_id = $data['pic_contact_id'] ?? null;
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
                ]),
            ])
            ->emptyStateHeading('No sent supplier orders')
            ->emptyStateDescription('Supplier orders will appear here once sent. Create shipments to track deliveries.')
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
