<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PNLStatus;
use App\Enums\QEStatus;
use App\Enums\RequestStage;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\AcceptanceReportsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerOrdersRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\CompletionReportsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\GoodsReceiveRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierOrdersRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierQuotesRelationManager;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * Guards the reconciliation between two orderings: the RequestStage enum
 * sequence and the tab-bar display sequence. They agree everywhere except
 * around Invoices (AWAITING_BUYER_CONFIRMATION), which sits after Supplier
 * Orders and Goods Receive in the tab bar despite a lower enum order. If a
 * future tab reorder or stage renumber reintroduces an out-of-order mismatch,
 * this matrix fails loudly.
 *
 * Shipments is intentionally excluded: it uses its own data-based getBadge()
 * (delivered-shipment check) via parent::getTabComponent(), not the stage
 * logic these eight tabs share. The Fulfillment group tab IS covered here at
 * position 6, via AcceptanceReportsRelationManager — the actual source
 * ViewRequest::fulfillmentTab() delegates to, since it does not override
 * getTabComponent() and therefore returns the shared trait stage badge.
 */
beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $buyer = Company::factory()->buyer()->for($this->team)->create();
    $supplier = Company::factory()->supplier()->for($this->team)->create();
    $currency = Currency::factory()->create(['code' => 'USD', 'is_default' => true]);

    $this->request = Request::factory()->for($this->team)->recycle($buyer)->create();

    // Satisfy every access gate so no tab is disabled and the badge reflects
    // pure stage progression: QE approved, PNL approved, an accepted buyer
    // quote (with none left in Sent), and a supplier order sent to the supplier.
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
        ->create(['request_id' => $this->request, 'buyer_id' => $buyer]);

    SupplierOrder::factory()
        ->for($this->team)
        ->recycle($this->request)
        ->recycle($supplier)
        ->recycle($currency)
        ->withStatus(OrderStatus::SENT)
        ->create();
});

it('shows the correct completion badge on every stage-based tab for each workflow stage', function (): void {
    // Each tab: its associated stage and its position in the tab bar (display order).
    $tabs = [
        ['class' => ItemsRelationManager::class, 'stage' => RequestStage::DRAFT, 'pos' => 0],
        ['class' => SupplierQuotesRelationManager::class, 'stage' => RequestStage::AWAITING_SUPPLIER_RESPONSE, 'pos' => 1],
        ['class' => BuyerQuotesRelationManager::class, 'stage' => RequestStage::PREPARING_BUYER_QUOTE, 'pos' => 2],
        ['class' => SupplierOrdersRelationManager::class, 'stage' => RequestStage::PREPARING_SUPPLIER_ORDER, 'pos' => 3],
        ['class' => GoodsReceiveRelationManager::class, 'stage' => RequestStage::GOODS_RECEIVE, 'pos' => 4],
        ['class' => BuyerOrdersRelationManager::class, 'stage' => RequestStage::AWAITING_BUYER_CONFIRMATION, 'pos' => 5],
        ['class' => CompletionReportsRelationManager::class, 'stage' => RequestStage::DELIVERED, 'pos' => 7],
        ['class' => AcceptanceReportsRelationManager::class, 'stage' => RequestStage::AWAITING_SHIPMENT, 'pos' => 6],
    ];

    // Every linear stage mapped to the furthest tab-bar position it represents.
    // The Fulfillment tab (badge sourced from AcceptanceReports) sits at pos 6;
    // shipped/delivered are past it; invoiced+ are past every tab.
    $currentPos = [
        'draft' => 0,
        'awaiting_supplier_response' => 1,
        'preparing_buyer_quote' => 2,
        'preparing_supplier_order' => 3,
        'goods_receive' => 4,
        'awaiting_buyer_confirmation' => 5,
        'awaiting_shipment' => 6,
        'shipped' => 7,
        'delivered' => 7,
        'invoiced' => 8,
        'paid' => 8,
        'completed' => 8,
    ];

    foreach ($currentPos as $stageValue => $curPos) {
        $currentStage = RequestStage::from($stageValue);
        $this->request->update(['stage' => $currentStage]);
        $record = $this->request->fresh();

        foreach ($tabs as $tab) {
            $expected = match (true) {
                $curPos > $tab['pos'] => '✓',
                $currentStage === $tab['stage'] => '●',
                default => null,
            };

            $badge = $tab['class']::getTabComponent($record, ViewRequest::class)->getBadge();

            expect($badge)->toBe(
                $expected,
                sprintf(
                    'Tab %s at current stage %s: expected %s, got %s',
                    class_basename($tab['class']),
                    $stageValue,
                    $expected ?? 'null',
                    $badge ?? 'null',
                )
            );
        }
    }
});
