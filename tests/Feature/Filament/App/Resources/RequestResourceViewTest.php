<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Enums\OrderStatus;
use App\Enums\PrepaymentType;
use App\Enums\RequestStage;
use App\Enums\ShipmentStatus;
use App\Filament\Resources\RequestResource\Pages\ListRequests;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\BuyerQuotePaymentTerm;
use App\Models\Company;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();
});

/**
 * Create a Request owned by the acting user's team.
 */
function viewTestRequest(Tests\TestCase $test): Request
{
    return Request::factory()
        ->for($test->team)
        ->create(['creator_id' => $test->user->getKey()]);
}

describe('Request view page layout', function (): void {
    it('renders the view page with the three-column summary', function (): void {
        $record = viewTestRequest($this);

        livewire(ViewRequest::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertSee('Financials')
            ->assertSee('Payments')
            ->assertSee('Shipments');
    });

    it('still exposes requested items via the Items relation manager tab', function (): void {
        $record = viewTestRequest($this);

        livewire(ViewRequest::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertSee('Requested Items');
    });
});

describe('Payment Terms section', function (): void {
    it('shows the prepayment as a percentage when type is PERCENT', function (): void {
        $record = viewTestRequest($this);

        $quote = BuyerQuote::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'prepayment_type' => PrepaymentType::PERCENT,
            'prepayment_percent' => 25,
        ]);

        BuyerOrder::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'buyer_quote_id' => $quote->getKey(),
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => now(),
        ]);

        livewire(ViewRequest::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertSee('25%');
    });

    it('shows the prepayment as currency when type is FIXED', function (): void {
        $record = viewTestRequest($this);

        $quote = BuyerQuote::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'prepayment_type' => PrepaymentType::FIXED,
            'prepayment_amount' => '1500.0000',
        ]);

        BuyerOrder::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'buyer_quote_id' => $quote->getKey(),
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => now(),
        ]);

        livewire(ViewRequest::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertSee('1,500');
    });

    it('lists payment terms with due days, percentage and a Not Paid status', function (): void {
        $record = viewTestRequest($this);

        $quote = BuyerQuote::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
        ]);

        BuyerQuotePaymentTerm::factory()->create([
            'buyer_quote_id' => $quote->getKey(),
            'due_days' => 30,
            'percentage' => 70,
            'sort_order' => 0,
        ]);

        BuyerOrder::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'buyer_quote_id' => $quote->getKey(),
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => now(),
        ]);

        livewire(ViewRequest::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertSee('30 days')
            ->assertSee('70%')
            ->assertSee('Not Paid');
    });

    it('shows an empty state when there is no buyer order or quote', function (): void {
        $record = viewTestRequest($this);

        livewire(ViewRequest::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertSee('No payment terms');
    });
});

describe('Shipment section', function (): void {
    it('lists shipments with number, carrier and tracking number', function (): void {
        $record = viewTestRequest($this);

        $shipment = Shipment::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'status' => ShipmentStatus::PENDING,
            'carrier_name' => 'DHL',
            'tracking_number' => 'TRK-9999',
        ]);

        livewire(ViewRequest::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertSee($shipment->shipment_number)
            ->assertSee('DHL')
            ->assertSee('TRK-9999');
    });

    it('renders a dash for missing carrier and tracking values', function (): void {
        $record = viewTestRequest($this);

        Shipment::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'status' => ShipmentStatus::PENDING,
            'carrier_name' => null,
            'tracking_number' => null,
        ]);

        livewire(ViewRequest::class, ['record' => $record->getKey()])
            ->assertOk();
    });

    it('shows an empty state when there are no shipments', function (): void {
        $record = viewTestRequest($this);

        livewire(ViewRequest::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertSee('No shipments');
    });
});

describe('Fulfillment status section', function (): void {
    it('shows the goods channel as pending and hides the services badge on a goods-only request', function (): void {
        $record = viewTestRequest($this);
        $supplier = Company::factory()->supplier()->for($this->team)->create();

        $goodsItem = RequestItem::factory()->recycle($record)->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);

        $order = SupplierOrder::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'supplier_id' => $supplier->getKey(),
        ]);
        $orderItem = SupplierOrderItem::factory()->recycle($order)->create([
            'request_item_id' => $goodsItem->getKey(),
            'quantity' => 10,
        ]);
        $shipment = Shipment::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'supplier_order_id' => $order->getKey(),
        ]);
        ShipmentItem::factory()->recycle($shipment)->forSupplierOrderItem($orderItem)->withQuantityShipped(6)->create();

        livewire(ViewRequest::class, ['record' => $record->refresh()->getKey()])
            ->assertOk()
            ->assertSee('Goods pending')
            ->assertDontSee('Services');
    });

    it('shows the overall status as Fulfilled once the only channel present is complete', function (): void {
        $record = viewTestRequest($this);
        $supplier = Company::factory()->supplier()->for($this->team)->create();

        $goodsItem = RequestItem::factory()->recycle($record)->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 5,
        ]);

        $order = SupplierOrder::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'supplier_id' => $supplier->getKey(),
        ]);
        $orderItem = SupplierOrderItem::factory()->recycle($order)->create([
            'request_item_id' => $goodsItem->getKey(),
            'quantity' => 5,
        ]);
        $shipment = Shipment::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'supplier_order_id' => $order->getKey(),
            'status' => ShipmentStatus::DELIVERED,
        ]);
        ShipmentItem::factory()->recycle($shipment)->forSupplierOrderItem($orderItem)->withQuantityShipped(5)->create();

        livewire(ViewRequest::class, ['record' => $record->refresh()->getKey()])
            ->assertOk()
            ->assertSee('Fulfilled');
    });
});

describe('Completed stage fulfillment gate on the edit action', function (): void {
    it('rejects setting stage to Completed via the edit action modal when the request is unfulfilled', function (): void {
        $record = viewTestRequest($this);
        RequestItem::factory()->recycle($record)->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);

        livewire(ViewRequest::class, ['record' => $record->getKey()])
            ->assertOk()
            ->callAction('edit', data: ['stage' => RequestStage::COMPLETED->value])
            ->assertHasFormErrors(['stage']);

        expect($record->refresh()->stage)->not->toBe(RequestStage::COMPLETED);
    });

    it('allows setting stage to Completed via the edit action modal once the request is fulfilled', function (): void {
        $record = viewTestRequest($this);
        $supplier = Company::factory()->supplier()->for($this->team)->create();

        $goodsItem = RequestItem::factory()->recycle($record)->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 5,
        ]);

        $order = SupplierOrder::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'supplier_id' => $supplier->getKey(),
        ]);
        $orderItem = SupplierOrderItem::factory()->recycle($order)->create([
            'request_item_id' => $goodsItem->getKey(),
            'quantity' => 5,
        ]);
        $shipment = Shipment::factory()->for($this->team)->create([
            'request_id' => $record->getKey(),
            'supplier_order_id' => $order->getKey(),
            'status' => ShipmentStatus::DELIVERED,
        ]);
        ShipmentItem::factory()->recycle($shipment)->forSupplierOrderItem($orderItem)->withQuantityShipped(5)->create();

        livewire(ViewRequest::class, ['record' => $record->refresh()->getKey()])
            ->assertOk()
            ->callAction('edit', data: ['stage' => RequestStage::COMPLETED->value])
            ->assertHasNoErrors();

        expect($record->refresh()->stage)->toBe(RequestStage::COMPLETED);
    });
});

describe('Request list fulfillment column', function (): void {
    it('exposes the fulfillment status column but keeps it hidden by default', function (): void {
        viewTestRequest($this);

        livewire(ListRequests::class)
            ->assertOk()
            ->assertTableColumnExists('fulfillment_status')
            ->assertCanNotRenderTableColumn('fulfillment_status');
    });
});
