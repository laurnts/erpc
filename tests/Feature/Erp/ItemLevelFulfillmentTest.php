<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Enums\RequestStage;
use App\Models\BuyerQuoteItem;
use App\Models\Company;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
    $this->actingAs($this->user);
});

describe('Item type classification', function (): void {
    it('defaults new request items to goods', function (): void {
        $item = RequestItem::factory()->recycle($this->request)->create();

        expect($item->refresh()->item_type)->toBe(ItemType::GOODS);
    });

    it('stores an explicit services item type', function (): void {
        $item = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::SERVICE,
        ]);

        expect($item->refresh()->item_type)->toBe(ItemType::SERVICE);
    });

    it('makes child items inherit the parent item type', function (): void {
        $main = RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::SERVICE,
        ]);

        $child = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => $main->getKey(),
        ]);

        expect($child->refresh()->item_type)->toBe(ItemType::SERVICE);
    });
});

describe('Item presence helpers', function (): void {
    it('reports goods and services item presence independently', function (): void {
        RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::GOODS,
        ]);

        expect($this->request->hasGoodsItems())->toBeTrue()
            ->and($this->request->hasServiceItems())->toBeFalse();

        RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::SERVICE,
        ]);

        expect($this->request->hasGoodsItems())->toBeTrue()
            ->and($this->request->hasServiceItems())->toBeTrue();
    });
});

describe('Item type summary', function (): void {
    it('summarizes item types as Goods, Services, or Mixed', function (): void {
        expect($this->request->item_type_summary)->toBe('—');

        $goods = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::GOODS,
        ]);
        expect($this->request->refresh()->item_type_summary)->toBe('Goods');

        RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::SERVICE,
        ]);
        expect($this->request->refresh()->item_type_summary)->toBe('Mixed');

        $goods->delete();
        expect($this->request->refresh()->item_type_summary)->toBe('Services');
    });
});

describe('Stage matching validation', function (): void {
    it('allows transition when goods items and services main items are matched, child unmatched', function (): void {
        RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::GOODS,
        ]);
        $main = RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::SERVICE,
        ]);
        RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => $main->getKey(),
            'is_matched' => false,
        ]);

        $this->request->refresh()->transitionTo(RequestStage::AWAITING_SUPPLIER_RESPONSE);

        expect($this->request->refresh()->stage)->toBe(RequestStage::AWAITING_SUPPLIER_RESPONSE);
    });

    it('blocks transition when a goods item is unmatched', function (): void {
        RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::GOODS,
            'is_matched' => false,
        ]);

        $this->request->refresh()->transitionTo(RequestStage::AWAITING_SUPPLIER_RESPONSE);
    })->throws(InvalidArgumentException::class);
});

describe('Fulfillment tab visibility', function (): void {
    it('shows both fulfillment tabs on a mixed request and only the matching one otherwise', function (): void {
        $viewPage = \App\Filament\Resources\RequestResource\Pages\ViewRequest::class;
        $shipmentsTab = \App\Filament\Resources\RequestResource\RelationManagers\ShipmentsRelationManager::class;
        $acceptanceTab = \App\Filament\Resources\RequestResource\RelationManagers\AcceptanceReportsRelationManager::class;

        RequestItem::factory()->recycle($this->request)->create(['item_type' => ItemType::GOODS]);

        expect($shipmentsTab::canViewForRecord($this->request->refresh(), $viewPage))->toBeTrue()
            ->and($acceptanceTab::canViewForRecord($this->request, $viewPage))->toBeFalse();

        RequestItem::factory()->recycle($this->request)->create(['item_type' => ItemType::SERVICE]);

        expect($shipmentsTab::canViewForRecord($this->request->refresh(), $viewPage))->toBeTrue()
            ->and($acceptanceTab::canViewForRecord($this->request, $viewPage))->toBeTrue();
    });
});

describe('Mixed request quoting', function (): void {
    it('generates one quote with flat goods lines and nested services child lines', function (): void {
        \App\Models\Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
        $supplier = Company::factory()->supplier()->recycle($this->team)->create();
        $goodsArticle = \App\Models\Article::factory()->recycle($this->team)->create();
        $serviceArticle = \App\Models\Article::factory()->recycle($this->team)->create();

        Illuminate\Support\Facades\DB::table('supplier_articles')->insert([
            ['article_id' => $goodsArticle->getKey(), 'supplier_id' => $supplier->getKey(), 'is_active' => true, 'is_preferred' => false, 'created_at' => now(), 'updated_at' => now()],
            ['article_id' => $serviceArticle->getKey(), 'supplier_id' => $supplier->getKey(), 'is_active' => true, 'is_preferred' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::GOODS,
            'article_id' => $goodsArticle->getKey(),
            'is_matched' => true,
        ]);
        $serviceMain = RequestItem::factory()->recycle($this->request)->create([
            'item_type' => ItemType::SERVICE,
            'article_id' => $serviceArticle->getKey(),
            'is_matched' => true,
        ]);
        $child = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => $serviceMain->getKey(),
        ]);

        $quotes = app(\App\Actions\Erp\GenerateSupplierQuotesForRequest::class)->execute($this->request);

        expect($quotes)->toHaveCount(1);

        $quoteItems = $quotes->first()->items()->get();

        expect($quoteItems)->toHaveCount(3)
            ->and($quoteItems->pluck('request_item_id'))->toContain($child->getKey());
    });
});

describe('Totals with mixed items', function (): void {
    it('always excludes child lines from totals', function (): void {
        $main = RequestItem::factory()->recycle($this->request)->matched()->create([
            'item_type' => ItemType::SERVICE,
        ]);
        $child = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => $main->getKey(),
        ]);

        $mainLine = new BuyerQuoteItem([
            'line_subtotal' => 100,
            'line_tax' => 0,
            'line_total' => 100,
            'cost_price' => 60,
            'quantity' => 1,
        ]);
        $mainLine->setRelation('requestItem', $main);

        $childLine = new BuyerQuoteItem([
            'line_subtotal' => 40,
            'line_tax' => 0,
            'line_total' => 40,
            'cost_price' => 25,
            'quantity' => 1,
        ]);
        $childLine->setRelation('requestItem', $child);

        $totals = BuyerQuoteItem::collectTotals(new Collection([$mainLine, $childLine]));

        expect($totals->subtotal)->toBe(100.0);
    });
});
