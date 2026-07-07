<?php

declare(strict_types=1);

use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

describe('Email item hierarchy', function (): void {
    it('organizes buyer order items with children directly under their parent', function (): void {
        $mainRequestItem = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => null,
        ]);
        $childRequestItem = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => $mainRequestItem->getKey(),
        ]);

        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        BuyerOrderItem::factory()->forBuyerOrder($order)->create([
            'request_item_id' => $mainRequestItem->getKey(),
            'description' => 'Main service',
            'sort_order' => 1,
        ]);
        BuyerOrderItem::factory()->forBuyerOrder($order)->create([
            'request_item_id' => $childRequestItem->getKey(),
            'description' => 'Child detail work',
            'sort_order' => 2,
        ]);

        $organized = BuyerOrderItem::organizeHierarchically($order->items()->with('requestItem')->get());

        expect($organized)->toHaveCount(2)
            ->and($organized[0]['is_child'])->toBeFalse()
            ->and($organized[0]['item']->description)->toBe('Main service')
            ->and($organized[1]['is_child'])->toBeTrue()
            ->and($organized[1]['item']->description)->toBe('Child detail work');
    });

    it('renders buyer order email child items with hierarchy markers', function (): void {
        $mainRequestItem = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => null,
        ]);
        $childRequestItem = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => $mainRequestItem->getKey(),
        ]);

        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        BuyerOrderItem::factory()->forBuyerOrder($order)->create([
            'request_item_id' => $mainRequestItem->getKey(),
            'description' => 'Pekerjaan pengaspalan jalan',
            'sort_order' => 1,
        ]);
        BuyerOrderItem::factory()->forBuyerOrder($order)->create([
            'request_item_id' => $childRequestItem->getKey(),
            'description' => 'Persiapan pengaspalan jalan 1',
            'sort_order' => 2,
        ]);

        $order->load(['items.requestItem', 'buyerQuote.currency']);

        $html = view('emails.partials.buyer-order-items-table', ['order' => $order])->render();

        expect($html)
            ->toContain('Pekerjaan pengaspalan jalan')
            ->toContain('Persiapan pengaspalan jalan 1')
            ->toContain('↳');
    });

    it('organizes supplier quote items with children directly under their parent', function (): void {
        $supplier = Company::factory()->supplier()->recycle($this->team)->create();
        $mainRequestItem = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => null,
            'supplier_id' => $supplier->getKey(),
        ]);
        $childRequestItem = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => $mainRequestItem->getKey(),
            'supplier_id' => $supplier->getKey(),
        ]);

        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($supplier)
            ->create();

        SupplierQuoteItem::factory()->forSupplierQuote($quote)->create([
            'request_item_id' => $mainRequestItem->getKey(),
            'description' => 'Main service',
            'sort_order' => 1,
        ]);
        SupplierQuoteItem::factory()->forSupplierQuote($quote)->create([
            'request_item_id' => $childRequestItem->getKey(),
            'description' => 'Child detail work',
            'sort_order' => 2,
        ]);

        $organized = SupplierQuoteItem::organizeHierarchically($quote->items()->with('requestItem')->get());

        expect($organized)->toHaveCount(2)
            ->and($organized[0]['is_child'])->toBeFalse()
            ->and($organized[1]['is_child'])->toBeTrue()
            ->and($organized[1]['item']->description)->toBe('Child detail work');
    });
});
