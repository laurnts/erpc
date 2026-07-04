<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Enums\RequestStage;
use App\Models\AcceptanceReport;
use App\Models\Company;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
    $this->actingAs($this->user);
});

/**
 * Ship a request item for the given quantity via a supplier order, recording
 * a shipment covering only $quantityShipped of it.
 */
function shipGoodsItem(Request $request, Team $team, Company $supplier, RequestItem $requestItem, float $quantityShipped): ShipmentItem
{
    $order = SupplierOrder::factory()->recycle($team)->recycle($request)->recycle($supplier)->create();

    $orderItem = SupplierOrderItem::factory()->recycle($order)->create([
        'request_item_id' => $requestItem->getKey(),
        'quantity' => $requestItem->quantity,
    ]);

    $shipment = Shipment::factory()->recycle($team)->recycle($request)->create([
        'supplier_order_id' => $order->getKey(),
    ]);

    return ShipmentItem::factory()
        ->recycle($shipment)
        ->forSupplierOrderItem($orderItem)
        ->withQuantityShipped($quantityShipped)
        ->create();
}

/**
 * Cover a services request item with an acceptance report.
 */
function acceptServiceItem(Request $request, RequestItem $requestItem): AcceptanceReport
{
    $report = AcceptanceReport::factory()->create(['request_id' => $request->getKey()]);
    $report->items()->attach($requestItem->getKey());

    return $report;
}

describe('Derived fulfillment completion', function (): void {
    it('is fulfilled when goods are fully shipped and services are accepted on a mixed request', function (): void {
        $goodsItem = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);
        $serviceItem = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::SERVICE,
        ]);

        shipGoodsItem($this->request, $this->team, $this->supplier, $goodsItem, 10);
        acceptServiceItem($this->request, $serviceItem);

        expect($this->request->goodsChannelComplete())->toBeTrue()
            ->and($this->request->servicesChannelComplete())->toBeTrue()
            ->and($this->request->isFulfilled())->toBeTrue();
    });

    it('leaves the goods channel incomplete when shipments only partially cover the quantity', function (): void {
        $goodsItem = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);

        shipGoodsItem($this->request, $this->team, $this->supplier, $goodsItem, 6);

        expect($this->request->goodsChannelComplete())->toBeFalse()
            ->and($this->request->isFulfilled())->toBeFalse();
    });

    it('is fulfilled on a services-only request once every main item is accepted, vacuous goods channel', function (): void {
        $serviceItem = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::SERVICE,
        ]);

        acceptServiceItem($this->request, $serviceItem);

        expect($this->request->goodsChannelComplete())->toBeTrue()
            ->and($this->request->servicesChannelComplete())->toBeTrue()
            ->and($this->request->isFulfilled())->toBeTrue();
    });

    it('is fulfilled on a goods-only request once fully shipped, vacuous services channel', function (): void {
        $goodsItem = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 5,
        ]);

        shipGoodsItem($this->request, $this->team, $this->supplier, $goodsItem, 5);

        expect($this->request->servicesChannelComplete())->toBeTrue()
            ->and($this->request->goodsChannelComplete())->toBeTrue()
            ->and($this->request->isFulfilled())->toBeTrue();
    });

    it('lets child service items ride on their parent acceptance coverage', function (): void {
        $mainItem = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::SERVICE,
        ]);
        RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => $mainItem->getKey(),
        ]);

        acceptServiceItem($this->request, $mainItem);

        expect($this->request->servicesChannelComplete())->toBeTrue()
            ->and($this->request->isFulfilled())->toBeTrue();
    });

    it('sums shipped quantity across multiple shipments to reach coverage', function (): void {
        $goodsItem = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);

        shipGoodsItem($this->request, $this->team, $this->supplier, $goodsItem, 6);
        expect($this->request->goodsChannelComplete())->toBeFalse();

        shipGoodsItem($this->request, $this->team, $this->supplier, $goodsItem, 4);
        expect($this->request->goodsChannelComplete())->toBeTrue()
            ->and($this->request->isFulfilled())->toBeTrue();
    });
});

describe('Fulfillment coverage excludes soft-deleted and failed documents', function (): void {
    it('flips goodsChannelComplete back to false when the covering shipment is soft-deleted', function (): void {
        $goodsItem = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);

        $shipmentItem = shipGoodsItem($this->request, $this->team, $this->supplier, $goodsItem, 10);
        expect($this->request->goodsChannelComplete())->toBeTrue();

        $shipmentItem->shipment->delete();

        expect($this->request->goodsChannelComplete())->toBeFalse();
    });

    it('flips servicesChannelComplete back to false when the covering acceptance report is soft-deleted', function (): void {
        $serviceItem = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::SERVICE,
        ]);

        $report = acceptServiceItem($this->request, $serviceItem);
        expect($this->request->servicesChannelComplete())->toBeTrue();

        $report->delete();

        expect($this->request->servicesChannelComplete())->toBeFalse();
    });

    it('does not count a FAILED shipment as delivery coverage', function (): void {
        $goodsItem = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);

        $shipmentItem = shipGoodsItem($this->request, $this->team, $this->supplier, $goodsItem, 10);
        expect($this->request->goodsChannelComplete())->toBeTrue();

        $shipmentItem->shipment->update(['status' => \App\Enums\ShipmentStatus::FAILED]);

        expect($this->request->goodsChannelComplete())->toBeFalse();
    });
});

describe('Fulfillment status label', function (): void {
    it('names only the pending channels the request actually has items for', function (): void {
        $goodsItem = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);

        expect($this->request->fulfillmentStatusLabel())->toBe('Goods pending');

        shipGoodsItem($this->request, $this->team, $this->supplier, $goodsItem, 10);
        expect($this->request->refresh()->fulfillmentStatusLabel())->toBe('Fulfilled');

        $serviceItem = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::SERVICE,
        ]);
        expect($this->request->refresh()->fulfillmentStatusLabel())->toBe('Services pending');

        acceptServiceItem($this->request, $serviceItem);
        expect($this->request->refresh()->fulfillmentStatusLabel())->toBe('Fulfilled');
    });

    it('reports both channels pending on a mixed request with neither channel complete', function (): void {
        RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);
        RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::SERVICE,
        ]);

        expect($this->request->fulfillmentStatusLabel())->toBe('Goods & services pending');
    });
});

describe('Completed stage fulfillment gate', function (): void {
    it('blocks the transition to Completed when the goods channel is incomplete, naming it', function (): void {
        RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);
        $this->request->update(['stage' => RequestStage::PAID]);

        expect(fn () => $this->request->refresh()->transitionTo(RequestStage::COMPLETED))
            ->toThrow(InvalidArgumentException::class, 'goods items not fully shipped');

        expect($this->request->refresh()->stage)->toBe(RequestStage::PAID);
    });

    it('blocks the transition to Completed when the services channel is incomplete, naming it', function (): void {
        RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::SERVICE,
        ]);
        $this->request->update(['stage' => RequestStage::PAID]);

        expect(fn () => $this->request->refresh()->transitionTo(RequestStage::COMPLETED))
            ->toThrow(InvalidArgumentException::class, 'services items without acceptance report');

        expect($this->request->refresh()->stage)->toBe(RequestStage::PAID);
    });

    it('names both channels when neither is complete on a mixed request', function (): void {
        RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);
        RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::SERVICE,
        ]);
        $this->request->update(['stage' => RequestStage::PAID]);

        expect(fn () => $this->request->refresh()->transitionTo(RequestStage::COMPLETED))
            ->toThrow(InvalidArgumentException::class, 'goods items not fully shipped; services items without acceptance report');
    });

    it('allows the transition to Completed once both channels are complete', function (): void {
        $goodsItem = RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);
        $serviceItem = RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::SERVICE,
        ]);

        shipGoodsItem($this->request, $this->team, $this->supplier, $goodsItem, 10);
        acceptServiceItem($this->request, $serviceItem);

        $this->request->update(['stage' => RequestStage::PAID]);
        $this->request->refresh()->transitionTo(RequestStage::COMPLETED);

        expect($this->request->refresh()->stage)->toBe(RequestStage::COMPLETED);
    });
});

describe('Completed stage fulfillment gate is enforced on any update, not just transitionTo()', function (): void {
    it('rejects a plain ->update() that sets stage to Completed on an unfulfilled request', function (): void {
        RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);
        $this->request->update(['stage' => RequestStage::PAID]);

        expect(fn () => $this->request->refresh()->update(['stage' => RequestStage::COMPLETED]))
            ->toThrow(\Illuminate\Validation\ValidationException::class, 'goods items not fully shipped');

        expect($this->request->refresh()->stage)->toBe(RequestStage::PAID);
    });

    it('allows a plain ->update() that sets stage to Completed once the request is fulfilled', function (): void {
        $goodsItem = RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);
        shipGoodsItem($this->request, $this->team, $this->supplier, $goodsItem, 10);
        $this->request->update(['stage' => RequestStage::PAID]);

        $this->request->refresh()->update(['stage' => RequestStage::COMPLETED]);

        expect($this->request->refresh()->stage)->toBe(RequestStage::COMPLETED);
    });

    it('does not gate updates that leave the stage untouched, even on an unfulfilled request', function (): void {
        RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::GOODS,
            'quantity' => 10,
        ]);
        $this->request->update(['stage' => RequestStage::PAID]);

        $this->request->update(['internal_notes' => 'unrelated update']);

        expect($this->request->refresh()->internal_notes)->toBe('unrelated update')
            ->and($this->request->stage)->toBe(RequestStage::PAID);
    });
});
