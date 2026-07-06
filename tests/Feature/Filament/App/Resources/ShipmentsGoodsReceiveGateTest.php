<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Enums\OrderStatus;
use App\Enums\PNLStatus;
use App\Enums\QEStatus;
use App\Enums\RequestStage;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\ShipmentsRelationManager;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\PaymentDocumentApproval;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierOrder;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
    $this->supplier = Company::factory()->supplier()->for($this->team)->create();

    $this->request = Request::factory()
        ->for($this->team)
        ->recycle($this->buyer)
        ->create(['stage' => RequestStage::AWAITING_SHIPMENT]);

    RequestItem::factory()->for($this->request)->create(['item_type' => ItemType::GOODS]);
    RequestItem::factory()->for($this->request)->create(['item_type' => ItemType::SERVICE]);

    // Satisfy the HasRequestStageTab mount guards (QE approved, PNL approved,
    // accepted buyer quote) so the only remaining gate under test is the
    // goods-receive approval check.
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

    $this->supplierOrder = SupplierOrder::factory()
        ->for($this->team)
        ->recycle($this->request)
        ->recycle($this->supplier)
        ->withStatus(OrderStatus::SENT)
        ->create();
});

/**
 * Attach unapproved goods-receive media to the request, mirroring the setup
 * used by GoodsReceiveApprovalActionTest's goodsReceiveBatch() helper.
 *
 * @return array<int, int> media IDs created
 */
function attachGoodsReceiveMedia(Request $request, int $count = 1): array
{
    $mediaIds = [];

    for ($i = 0; $i < $count; $i++) {
        $media = $request->media()->create([
            'uuid' => Str::uuid()->toString(),
            'collection_name' => 'goods_receive',
            'name' => 'goods-receive-doc-'.$i,
            'file_name' => 'goods-receive-doc-'.$i.'.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'local',
            'conversions_disk' => 'local',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $mediaIds[] = $media->getKey();
    }

    return $mediaIds;
}

/**
 * Approve every given media id (i.e. no unapproved goods-receive documents remain).
 *
 * @param  array<int, int>  $mediaIds
 */
function approveGoodsReceiveMedia(Tests\TestCase $test, array $mediaIds): void
{
    foreach ($mediaIds as $mediaId) {
        PaymentDocumentApproval::create([
            'team_id' => $test->team->getKey(),
            'media_id' => $mediaId,
            'user_id' => $test->user->getKey(),
            'approved_at' => now(),
        ]);
    }
}

it('does not redirect away from the Fulfillment tab on a mixed deal with unapproved goods-receive documents', function (): void {
    attachGoodsReceiveMedia($this->request);

    livewire(ShipmentsRelationManager::class, [
        'ownerRecord' => $this->request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->assertNoRedirect();
});

it('disables the create_shipment action while goods-receive documents are unapproved', function (): void {
    attachGoodsReceiveMedia($this->request);

    livewire(ShipmentsRelationManager::class, [
        'ownerRecord' => $this->request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertTableActionDisabled('create_shipment', record: $this->supplierOrder);
});

it('enables the create_shipment action once all goods-receive documents are approved', function (): void {
    $mediaIds = attachGoodsReceiveMedia($this->request);
    approveGoodsReceiveMedia($this, $mediaIds);

    livewire(ShipmentsRelationManager::class, [
        'ownerRecord' => $this->request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertTableActionEnabled('create_shipment', record: $this->supplierOrder);
});
