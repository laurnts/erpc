<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PNLStatus;
use App\Enums\QEStatus;
use App\Enums\RequestStage;
use App\Filament\Resources\RequestResource;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\GoodsReceiveRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierOrdersRelationManager;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
    $this->supplier = Company::factory()->supplier()->for($this->team)->create();
    $this->currency = Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    $this->request = Request::factory()->for($this->team)->recycle($this->buyer)->create([
        'stage' => RequestStage::PREPARING_SUPPLIER_ORDER,
    ]);

    QuotationEvaluation::factory()
        ->recycle($this->user)
        ->forRequest($this->request)
        ->create(['status' => QEStatus::APPROVED]);

    ProfitAndLoss::factory()
        ->recycle($this->user)
        ->forRequest($this->request)
        ->create(['status' => PNLStatus::APPROVED]);

    BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->user)
        ->accepted()
        ->create([
            'request_id' => $this->request,
            'buyer_id' => $this->buyer,
        ]);
});

/**
 * Create a supplier order on the request in the given status.
 */
function goodsReceiveGateOrder(Tests\TestCase $test, OrderStatus $status): SupplierOrder
{
    return SupplierOrder::factory()
        ->for($test->team)
        ->recycle($test->request)
        ->recycle($test->supplier)
        ->recycle($test->currency)
        ->withStatus($status)
        ->create();
}

function supplierOrdersTabUrl(Tests\TestCase $test): string
{
    return RequestResource::getUrl('view', [
        'record' => $test->request->getKey(),
        'activeRelationManager' => 'supplierOrders',
    ]);
}

describe('goods receive relation manager access', function (): void {
    it('redirects back to supplier orders when the request has no supplier orders at all', function (): void {
        livewire(GoodsReceiveRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => ViewRequest::class,
        ])->assertRedirect(supplierOrdersTabUrl($this));
    });

    it('redirects back to supplier orders when a supplier order is still awaiting approval', function (): void {
        goodsReceiveGateOrder($this, OrderStatus::CONFIRMED);

        livewire(GoodsReceiveRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => ViewRequest::class,
        ])->assertRedirect(supplierOrdersTabUrl($this));
    });

    it('redirects back to supplier orders when an order is approved but not yet sent to the supplier', function (): void {
        goodsReceiveGateOrder($this, OrderStatus::APPROVED);

        livewire(GoodsReceiveRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => ViewRequest::class,
        ])->assertRedirect(supplierOrdersTabUrl($this));
    });

    it('allows access once all supplier orders are sent', function (): void {
        goodsReceiveGateOrder($this, OrderStatus::SENT);

        livewire(GoodsReceiveRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => ViewRequest::class,
        ])->assertOk()->assertNoRedirect();
    });
});

describe('stage advance via tab click', function (): void {
    it('does not advance the stage to goods receive when no supplier orders exist', function (): void {
        livewire(ViewRequest::class, ['record' => $this->request->getKey()])
            ->set('activeRelationManager', 'goodsReceive');

        expect($this->request->refresh()->stage)->toBe(RequestStage::PREPARING_SUPPLIER_ORDER);
    });

    it('does not advance the stage while a supplier order awaits approval', function (): void {
        goodsReceiveGateOrder($this, OrderStatus::CONFIRMED);

        livewire(ViewRequest::class, ['record' => $this->request->getKey()])
            ->set('activeRelationManager', 'goodsReceive');

        expect($this->request->refresh()->stage)->toBe(RequestStage::PREPARING_SUPPLIER_ORDER);
    });

    it('does not advance the stage while an approved order has not been sent to the supplier', function (): void {
        goodsReceiveGateOrder($this, OrderStatus::APPROVED);

        livewire(ViewRequest::class, ['record' => $this->request->getKey()])
            ->set('activeRelationManager', 'goodsReceive');

        expect($this->request->refresh()->stage)->toBe(RequestStage::PREPARING_SUPPLIER_ORDER);
    });

    it('advances the stage to goods receive once all supplier orders are sent', function (): void {
        goodsReceiveGateOrder($this, OrderStatus::SENT);

        livewire(ViewRequest::class, ['record' => $this->request->getKey()])
            ->set('activeRelationManager', 'goodsReceive');

        expect($this->request->refresh()->stage)->toBe(RequestStage::GOODS_RECEIVE);
    });
});

describe('stage tab completion badges', function (): void {
    it('marks Supplier Orders and Goods Receive complete once the request reaches Invoices', function (): void {
        // Reaching Invoices (AWAITING_BUYER_CONFIRMATION) means both preceding
        // tabs have been passed, even though their enum order is higher.
        goodsReceiveGateOrder($this, OrderStatus::SENT);
        $this->request->update(['stage' => RequestStage::AWAITING_BUYER_CONFIRMATION]);
        $record = $this->request->refresh();

        $supplierOrdersTab = SupplierOrdersRelationManager::getTabComponent($record, ViewRequest::class);
        $goodsReceiveTab = GoodsReceiveRelationManager::getTabComponent($record, ViewRequest::class);

        expect($supplierOrdersTab->getBadge())->toBe('✓')
            ->and($goodsReceiveTab->getBadge())->toBe('✓');
    });

    it('does not mark Supplier Orders complete while the request is still preparing the supplier order', function (): void {
        goodsReceiveGateOrder($this, OrderStatus::SENT);
        $this->request->update(['stage' => RequestStage::PREPARING_SUPPLIER_ORDER]);
        $record = $this->request->refresh();

        $supplierOrdersTab = SupplierOrdersRelationManager::getTabComponent($record, ViewRequest::class);

        expect($supplierOrdersTab->getBadge())->toBe('●');
    });
});

describe('activeRelationManager query param routing', function (): void {
    it('translates a relation manager key to its Filament tab index', function (): void {
        expect(ViewRequest::relationManagerIndexForKey('supplierOrders'))->toBe('3')
            ->and(ViewRequest::relationManagerIndexForKey('goodsReceive'))->toBe('4')
            ->and(ViewRequest::relationManagerIndexForKey('items'))->toBe('0');
    });

    it('returns null for a missing or unknown key', function (): void {
        expect(ViewRequest::relationManagerIndexForKey(null))->toBeNull()
            ->and(ViewRequest::relationManagerIndexForKey('notATab'))->toBeNull()
            ->and(ViewRequest::relationManagerIndexForKey(3))->toBeNull();
    });
});
