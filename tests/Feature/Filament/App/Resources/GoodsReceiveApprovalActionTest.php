<?php

declare(strict_types=1);

use App\Enums\CentralPurchasingRole;
use App\Filament\Resources\GoodsReceiveApprovalResource\Pages\ListGoodsReceiveApprovals;
use App\Models\Company;
use App\Models\GoodsReceiveBatch;
use App\Models\Membership;
use App\Models\PaymentDocumentApproval;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();
});

/**
 * Grant the given user the finance-approver membership required by the approve action.
 */
function grantFinanceApproverRole(Tests\TestCase $test, ?User $user = null): void
{
    Membership::factory()->create([
        'team_id' => $test->team->getKey(),
        'user_id' => ($user ?? $test->user)->getKey(),
        'role' => 'central_purchasing',
        'central_purchasing_role' => CentralPurchasingRole::FINANCE,
        'is_approver' => true,
    ]);
}

/**
 * Create a goods receive batch (request -> supplier order -> batch) with media records.
 */
function goodsReceiveBatch(Tests\TestCase $test, int $mediaCount = 2): GoodsReceiveBatch
{
    $request = Request::factory()
        ->for($test->team)
        ->create(['creator_id' => $test->user->getKey()]);

    $supplier = Company::factory()->supplier()->for($test->team)->create();

    $order = SupplierOrder::factory()->for($test->team)->create([
        'request_id' => $request->getKey(),
        'supplier_id' => $supplier->getKey(),
    ]);

    $mediaIds = [];

    for ($i = 0; $i < $mediaCount; $i++) {
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
            'custom_properties' => [
                'uploaded_by' => $test->user->getKey(),
                'supplier_order_id' => $order->getKey(),
            ],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $mediaIds[] = $media->getKey();
    }

    return GoodsReceiveBatch::create([
        'request_id' => $request->getKey(),
        'supplier_order_id' => $order->getKey(),
        'user_id' => $test->user->getKey(),
        'media_ids' => $mediaIds,
    ]);
}

describe('approve action happy path', function (): void {
    it('approves all documents in the batch and records approver, notes and timestamp', function (): void {
        grantFinanceApproverRole($this);
        $batch = goodsReceiveBatch($this, mediaCount: 2);

        livewire(ListGoodsReceiveApprovals::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$batch])
            ->callAction(
                TestAction::make('approve')->table($batch),
                data: ['notes' => 'Checked against delivery note'],
            )
            ->assertHasNoErrors()
            ->assertNotified('Documents approved');

        expect(PaymentDocumentApproval::query()->count())->toBe(2);

        foreach ($batch->media_ids as $mediaId) {
            $approval = PaymentDocumentApproval::query()->where('media_id', $mediaId)->first();

            expect($approval)->not->toBeNull()
                ->and($approval->team_id)->toBe($this->team->getKey())
                ->and($approval->user_id)->toBe($this->user->getKey())
                ->and($approval->notes)->toBe('Checked against delivery note')
                ->and($approval->approved_at)->not->toBeNull();
        }
    });

    it('only creates approvals for documents not yet approved in a partially approved batch', function (): void {
        grantFinanceApproverRole($this);
        $batch = goodsReceiveBatch($this, mediaCount: 2);

        $otherApprover = User::factory()->create();
        grantFinanceApproverRole($this, $otherApprover);

        PaymentDocumentApproval::create([
            'team_id' => $this->team->getKey(),
            'media_id' => $batch->media_ids[0],
            'user_id' => $otherApprover->getKey(),
            'approved_at' => now(),
            'notes' => 'First document already approved',
        ]);

        livewire(ListGoodsReceiveApprovals::class)
            ->assertOk()
            ->callAction(TestAction::make('approve')->table($batch), data: ['notes' => null])
            ->assertHasNoErrors()
            ->assertNotified('Documents approved');

        expect(PaymentDocumentApproval::query()->count())->toBe(2)
            ->and(PaymentDocumentApproval::query()->where('media_id', $batch->media_ids[0])->count())->toBe(1)
            ->and(PaymentDocumentApproval::query()->where('media_id', $batch->media_ids[0])->value('user_id'))->toBe($otherApprover->getKey());

        $newApproval = PaymentDocumentApproval::query()->where('media_id', $batch->media_ids[1])->first();

        expect($newApproval)->not->toBeNull()
            ->and($newApproval->user_id)->toBe($this->user->getKey());
    });
});

describe('approve action authorization', function (): void {
    it('hides the approve action from a user without the finance approver membership', function (): void {
        $batch = goodsReceiveBatch($this);

        livewire(ListGoodsReceiveApprovals::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$batch])
            ->assertActionHidden(TestAction::make('approve')->table($batch));

        expect(PaymentDocumentApproval::query()->count())->toBe(0);
    });

    it('hides the approve action from a finance member who is not an approver', function (): void {
        Membership::factory()->create([
            'team_id' => $this->team->getKey(),
            'user_id' => $this->user->getKey(),
            'role' => 'central_purchasing',
            'central_purchasing_role' => CentralPurchasingRole::FINANCE,
            'is_approver' => false,
        ]);
        $batch = goodsReceiveBatch($this);

        livewire(ListGoodsReceiveApprovals::class)
            ->assertOk()
            ->assertActionHidden(TestAction::make('approve')->table($batch));
    });
});

describe('approve action guards', function (): void {
    it('hides the approve action once every document in the batch is approved', function (): void {
        grantFinanceApproverRole($this);
        $batch = goodsReceiveBatch($this, mediaCount: 1);

        PaymentDocumentApproval::create([
            'team_id' => $this->team->getKey(),
            'media_id' => $batch->media_ids[0],
            'user_id' => $this->user->getKey(),
            'approved_at' => now(),
        ]);

        livewire(ListGoodsReceiveApprovals::class)
            ->assertOk()
            ->assertActionHidden(TestAction::make('approve')->table($batch));

        expect(PaymentDocumentApproval::query()->count())->toBe(1);
    });

    it('hides the approve action for a batch without any documents', function (): void {
        grantFinanceApproverRole($this);
        $batch = goodsReceiveBatch($this, mediaCount: 0);

        livewire(ListGoodsReceiveApprovals::class)
            ->assertOk()
            ->assertActionHidden(TestAction::make('approve')->table($batch));
    });
});

it('does not list batches belonging to another team', function (): void {
    grantFinanceApproverRole($this);
    $batch = goodsReceiveBatch($this);

    $otherUser = User::factory()->withPersonalTeam()->create();
    $foreignRequest = Request::factory()
        ->for($otherUser->personalTeam())
        ->create(['creator_id' => $otherUser->getKey()]);
    $foreignOrder = SupplierOrder::factory()->for($otherUser->personalTeam())->create([
        'request_id' => $foreignRequest->getKey(),
        'po_number' => 'PO-2026-FOREIGN',
    ]);
    $foreignBatch = GoodsReceiveBatch::create([
        'request_id' => $foreignRequest->getKey(),
        'supplier_order_id' => $foreignOrder->getKey(),
        'user_id' => $otherUser->getKey(),
        'media_ids' => [],
    ]);

    livewire(ListGoodsReceiveApprovals::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$batch])
        ->assertCanNotSeeTableRecords([$foreignBatch]);
});
