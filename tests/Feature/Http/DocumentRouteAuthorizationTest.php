<?php

declare(strict_types=1);

use App\Models\BuyerQuote;
use App\Models\GoodsReceiveBatch;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

// --- Buyer Quote PO download ---

it('allows a same-team user to download the buyer quote PO', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $buyerQuote = BuyerQuote::factory()->for($owner->personalTeam())->create();
    $media = $buyerQuote->addMedia(UploadedFile::fake()->createWithContent('po.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('buyer_po');

    $response = $this->actingAs($owner)
        ->get(route('buyer-quotes.po.download', [$buyerQuote, $media]));

    $response->assertOk();
});

it('rejects a cross-team user downloading the buyer quote PO with 404', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $buyerQuote = BuyerQuote::factory()->for($owner->personalTeam())->create();
    $media = $buyerQuote->addMedia(UploadedFile::fake()->createWithContent('po.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('buyer_po');

    $intruder = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($intruder)
        ->get(route('buyer-quotes.po.download', [$buyerQuote, $media]));

    $response->assertNotFound();
});

it('redirects an unauthenticated request to download the buyer quote PO', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $buyerQuote = BuyerQuote::factory()->for($owner->personalTeam())->create();
    $media = $buyerQuote->addMedia(UploadedFile::fake()->createWithContent('po.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('buyer_po');

    $response = $this->get(route('buyer-quotes.po.download', [$buyerQuote, $media]));

    $response->assertRedirect(route('login'));
});

// --- Buyer Quote PO delete ---

it('allows a same-team user to delete the buyer quote PO', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $buyerQuote = BuyerQuote::factory()->for($owner->personalTeam())->create();
    $media = $buyerQuote->addMedia(UploadedFile::fake()->createWithContent('po.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('buyer_po');

    $response = $this->actingAs($owner)
        ->delete(route('buyer-quotes.po.delete', [$buyerQuote, $media]));

    $response->assertRedirect();
    expect($buyerQuote->fresh()->getMedia('buyer_po'))->toHaveCount(0);
});

it('rejects a cross-team user deleting the buyer quote PO with 404', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $buyerQuote = BuyerQuote::factory()->for($owner->personalTeam())->create();
    $media = $buyerQuote->addMedia(UploadedFile::fake()->createWithContent('po.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('buyer_po');

    $intruder = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($intruder)
        ->delete(route('buyer-quotes.po.delete', [$buyerQuote, $media]));

    $response->assertNotFound();
    expect($buyerQuote->fresh()->getMedia('buyer_po'))->toHaveCount(1);
});

it('redirects an unauthenticated request to delete the buyer quote PO', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $buyerQuote = BuyerQuote::factory()->for($owner->personalTeam())->create();
    $media = $buyerQuote->addMedia(UploadedFile::fake()->createWithContent('po.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('buyer_po');

    $response = $this->delete(route('buyer-quotes.po.delete', [$buyerQuote, $media]));

    $response->assertRedirect(route('login'));
});

// --- Supplier Quote quotation download ---

it('allows a same-team user to download the supplier quote quotation', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quotation.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    $response = $this->actingAs($owner)
        ->get(route('supplier-quotes.quotation.download', [$supplierQuote, $media]));

    $response->assertOk();
});

it('rejects a cross-team user downloading the supplier quote quotation with 404', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quotation.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    $intruder = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($intruder)
        ->get(route('supplier-quotes.quotation.download', [$supplierQuote, $media]));

    $response->assertNotFound();
});

it('redirects an unauthenticated request to download the supplier quote quotation', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quotation.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    $response = $this->get(route('supplier-quotes.quotation.download', [$supplierQuote, $media]));

    $response->assertRedirect(route('login'));
});

// --- Supplier Quote quotation delete ---

it('allows a same-team user to delete the supplier quote quotation', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quotation.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    $response = $this->actingAs($owner)
        ->delete(route('supplier-quotes.quotation.delete', [$supplierQuote, $media]));

    $response->assertRedirect();
    expect($supplierQuote->fresh()->getMedia('quotation'))->toHaveCount(0);
});

it('rejects a cross-team user deleting the supplier quote quotation with 404', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quotation.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    $intruder = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($intruder)
        ->delete(route('supplier-quotes.quotation.delete', [$supplierQuote, $media]));

    $response->assertNotFound();
    expect($supplierQuote->fresh()->getMedia('quotation'))->toHaveCount(1);
});

it('redirects an unauthenticated request to delete the supplier quote quotation', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quotation.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    $response = $this->delete(route('supplier-quotes.quotation.delete', [$supplierQuote, $media]));

    $response->assertRedirect(route('login'));
});

// --- Request goods receive delete ---

it('allows a same-team user to delete a goods receive document', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $requestModel = Request::factory()->for($owner->personalTeam())->create();
    $media = $requestModel->addMedia(UploadedFile::fake()->createWithContent('receipt.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('goods_receive');

    $response = $this->actingAs($owner)
        ->delete(route('requests.goods-receive.delete', [$requestModel, $media]));

    $response->assertRedirect();
    expect($requestModel->fresh()->getMedia('goods_receive'))->toHaveCount(0);
});

it('rejects a cross-team user deleting a goods receive document with 404', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $requestModel = Request::factory()->for($owner->personalTeam())->create();
    $media = $requestModel->addMedia(UploadedFile::fake()->createWithContent('receipt.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('goods_receive');

    $intruder = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($intruder)
        ->delete(route('requests.goods-receive.delete', [$requestModel, $media]));

    $response->assertNotFound();
    expect($requestModel->fresh()->getMedia('goods_receive'))->toHaveCount(1);
});

it('redirects an unauthenticated request to delete a goods receive document', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $requestModel = Request::factory()->for($owner->personalTeam())->create();
    $media = $requestModel->addMedia(UploadedFile::fake()->createWithContent('receipt.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('goods_receive');

    $response = $this->delete(route('requests.goods-receive.delete', [$requestModel, $media]));

    $response->assertRedirect(route('login'));
});

it('removes the deleted media id from its goods receive batch, keeping the batch when other media remain', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $requestModel = Request::factory()->for($owner->personalTeam())->create();
    $firstMedia = $requestModel->addMedia(UploadedFile::fake()->createWithContent('receipt-1.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('goods_receive');
    $secondMedia = $requestModel->addMedia(UploadedFile::fake()->createWithContent('receipt-2.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('goods_receive');

    $supplierOrder = SupplierOrder::factory()->for($owner->personalTeam())->forRequest($requestModel)->create();

    $batch = GoodsReceiveBatch::query()->create([
        'request_id' => $requestModel->id,
        'supplier_order_id' => $supplierOrder->id,
        'user_id' => $owner->id,
        'media_ids' => [$firstMedia->id, $secondMedia->id],
    ]);

    $this->actingAs($owner)
        ->delete(route('requests.goods-receive.delete', [$requestModel, $firstMedia]))
        ->assertRedirect();

    expect($batch->fresh()->media_ids)->toBe([$secondMedia->id]);
});

it('deletes the goods receive batch once its last media is removed', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $requestModel = Request::factory()->for($owner->personalTeam())->create();
    $media = $requestModel->addMedia(UploadedFile::fake()->createWithContent('receipt.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('goods_receive');

    $supplierOrder = SupplierOrder::factory()->for($owner->personalTeam())->forRequest($requestModel)->create();

    $batch = GoodsReceiveBatch::query()->create([
        'request_id' => $requestModel->id,
        'supplier_order_id' => $supplierOrder->id,
        'user_id' => $owner->id,
        'media_ids' => [$media->id],
    ]);

    $this->actingAs($owner)
        ->delete(route('requests.goods-receive.delete', [$requestModel, $media]))
        ->assertRedirect();

    expect(GoodsReceiveBatch::query()->find($batch->id))->toBeNull();
});
