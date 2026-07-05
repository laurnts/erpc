<?php

declare(strict_types=1);

use App\Enums\QEStatus;
use App\Models\GoodsReceiveBatch;
use App\Models\PaymentDocumentApproval;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create(['name' => 'Jun Sin']);
});

it('seeds a placeholder goods receive document and batch for a PO', function (): void {
    $request = Request::factory()->create(['team_id' => $this->team->id]);
    $order = SupplierOrder::factory()->create([
        'team_id' => $this->team->id,
        'request_id' => $request->id,
    ]);

    $this->artisan('request:seed-documents', [
        'identifier' => $order->po_number,
        '--gate' => 'goods-receive',
    ])->assertSuccessful();

    $media = $request->refresh()->getMedia('goods_receive');
    expect($media)->toHaveCount(1)
        ->and($media->first()->getCustomProperty('supplier_order_id'))->toBe($order->id)
        ->and($media->first()->getCustomProperty('uploaded_by'))->toBe($this->user->id)
        ->and(file_exists($media->first()->getPath()))->toBeTrue();

    $batch = GoodsReceiveBatch::query()->where('request_id', $request->id)->first();
    expect($batch)->not->toBeNull()
        ->and($batch->media_ids)->toBe([$media->first()->id])
        ->and($batch->supplier_order_id)->toBe($order->id);

    expect(PaymentDocumentApproval::query()->count())->toBe(0);
});

it('approves seeded goods receive documents with --approve', function (): void {
    $request = Request::factory()->create(['team_id' => $this->team->id]);
    $order = SupplierOrder::factory()->create([
        'team_id' => $this->team->id,
        'request_id' => $request->id,
    ]);

    $this->artisan('request:seed-documents', [
        'identifier' => $order->po_number,
        '--gate' => 'goods-receive',
        '--approve' => true,
    ])->assertSuccessful();

    $mediaId = $request->refresh()->getMedia('goods_receive')->first()->id;
    $approval = PaymentDocumentApproval::query()->where('media_id', $mediaId)->first();
    expect($approval)->not->toBeNull()
        ->and($approval->team_id)->toBe($this->team->id)
        ->and($approval->user_id)->toBe($this->user->id);
});

it('seeds a completion report payment document with payment terms', function (): void {
    $request = Request::factory()->create(['team_id' => $this->team->id]);

    $this->artisan('request:seed-documents', [
        'identifier' => $request->request_number,
        '--gate' => 'completion-report',
        '--payment-terms' => '50%',
    ])->assertSuccessful();

    $media = $request->refresh()->getMedia('completion_reports');
    expect($media)->toHaveCount(1)
        ->and($media->first()->getCustomProperty('is_payment_document'))->toBeTrue()
        ->and($media->first()->getCustomProperty('payment_terms'))->toBe('50%');

    expect(PaymentDocumentApproval::query()->count())->toBe(0);
});

it('approves a seeded completion report with --approve', function (): void {
    $request = Request::factory()->create(['team_id' => $this->team->id]);

    $this->artisan('request:seed-documents', [
        'identifier' => $request->request_number,
        '--gate' => 'completion-report',
        '--approve' => true,
    ])->assertSuccessful();

    $mediaId = $request->refresh()->getMedia('completion_reports')->first()->id;
    expect(PaymentDocumentApproval::query()->where('media_id', $mediaId)->exists())->toBeTrue();
});

it('seeds and approves quotation evaluation documents, flipping the QE to approved', function (): void {
    $request = Request::factory()->create(['team_id' => $this->team->id]);
    $qe = QuotationEvaluation::factory()->create([
        'team_id' => $this->team->id,
        'request_id' => $request->id,
    ]);

    $this->artisan('request:seed-documents', [
        'identifier' => $qe->qe_number,
        '--gate' => 'documents',
        '--approve' => true,
    ])->assertSuccessful();

    $media = $qe->refresh()->getMedia('documents');
    expect($media)->toHaveCount(1)
        ->and(PaymentDocumentApproval::query()->where('media_id', $media->first()->id)->exists())->toBeTrue()
        ->and($qe->status)->toBe(QEStatus::APPROVED);
});

it('seeds documents for a supplier order by PO number', function (): void {
    $order = SupplierOrder::factory()->create(['team_id' => $this->team->id]);

    $this->artisan('request:seed-documents', [
        'identifier' => $order->po_number,
        '--gate' => 'documents',
    ])->assertSuccessful();

    expect($order->refresh()->getMedia('documents'))->toHaveCount(1);
});

it('seeds a quotation document for a supplier quote by quote number', function (): void {
    $quote = SupplierQuote::factory()->create(['team_id' => $this->team->id]);

    $this->artisan('request:seed-documents', [
        'identifier' => $quote->quote_number,
        '--gate' => 'quotation',
    ])->assertSuccessful();

    expect($quote->refresh()->getMedia('quotation'))->toHaveCount(1);
});

it('seeds quotation documents for every supplier quote on a request that is missing one', function (): void {
    $request = Request::factory()->create(['team_id' => $this->team->id]);
    $bare = SupplierQuote::factory()->create([
        'team_id' => $this->team->id,
        'request_id' => $request->id,
    ]);
    $covered = SupplierQuote::factory()->create([
        'team_id' => $this->team->id,
        'request_id' => $request->id,
    ]);
    $this->artisan('request:seed-documents', [
        'identifier' => $covered->quote_number,
        '--gate' => 'quotation',
    ])->assertSuccessful();

    $this->artisan('request:seed-documents', [
        'identifier' => $request->request_number,
        '--gate' => 'quotation',
    ])->assertSuccessful();

    expect($bare->refresh()->getMedia('quotation'))->toHaveCount(1)
        ->and($covered->refresh()->getMedia('quotation'))->toHaveCount(1);
});

it('does not duplicate an existing quotation document for a single quote', function (): void {
    $quote = SupplierQuote::factory()->create(['team_id' => $this->team->id]);

    $this->artisan('request:seed-documents', [
        'identifier' => $quote->quote_number,
        '--gate' => 'quotation',
    ])->assertSuccessful();

    $this->artisan('request:seed-documents', [
        'identifier' => $quote->quote_number,
        '--gate' => 'quotation',
    ])->assertSuccessful();

    expect($quote->refresh()->getMedia('quotation'))->toHaveCount(1);
});

it('fails for an unknown quotation identifier', function (): void {
    $this->artisan('request:seed-documents', [
        'identifier' => 'SQ-9999-NOPE',
        '--gate' => 'quotation',
    ])->assertFailed();
});

it('fails for an unknown identifier', function (): void {
    $this->artisan('request:seed-documents', [
        'identifier' => 'PO-9999-NOPE',
        '--gate' => 'goods-receive',
    ])->assertFailed();
});

it('fails for a missing or invalid gate', function (): void {
    $request = Request::factory()->create(['team_id' => $this->team->id]);

    $this->artisan('request:seed-documents', [
        'identifier' => $request->request_number,
        '--gate' => 'nonsense',
    ])->assertFailed();

    $this->artisan('request:seed-documents', [
        'identifier' => $request->request_number,
    ])->assertFailed();
});

it('fails when the default seeding user does not exist', function (): void {
    $this->user->delete();
    $request = Request::factory()->create(['team_id' => $this->team->id]);

    $this->artisan('request:seed-documents', [
        'identifier' => $request->request_number,
        '--gate' => 'completion-report',
    ])->assertFailed();
});
