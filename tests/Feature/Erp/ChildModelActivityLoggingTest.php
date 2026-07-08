<?php

declare(strict_types=1);

use App\Enums\ActorType;
use App\Enums\QEStatus;
use App\Enums\ShipmentStatus;
use App\Models\AcceptanceReport;
use App\Models\ActivityLog;
use App\Models\GoodsReceiveBatch;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Shipment;
use App\Models\SupplierOrder;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\Relation;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team = $this->admin->personalTeam();

    actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team);
});

it('records shipment creation with the staff actor', function (): void {
    ActivityLog::query()->delete();

    $shipment = Shipment::factory()->recycle($this->team)->create();

    $activity = ActivityLog::query()
        ->where('subject_type', 'shipment')
        ->where('subject_id', $shipment->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->actor_type)->toBe(ActorType::Staff)
        ->and($activity->team_id)->toBe($this->team->id)
        ->and($activity->properties->get('attributes'))->toHaveKey('shipment_number', $shipment->shipment_number);
});

it('records a shipment status change with old and new values', function (): void {
    $shipment = Shipment::factory()->recycle($this->team)->pending()->create();

    ActivityLog::query()->delete();

    $shipment->update(['status' => ShipmentStatus::IN_TRANSIT]);

    $activity = ActivityLog::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('updated')
        ->and($activity->subject_type)->toBe('shipment')
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->properties->get('attributes'))->toMatchArray(['status' => ShipmentStatus::IN_TRANSIT->value])
        ->and($activity->properties->get('old'))->toMatchArray(['status' => ShipmentStatus::PENDING->value]);
});

it('skips logging when only non-audited shipment attributes change', function (): void {
    $shipment = Shipment::factory()->recycle($this->team)->create();

    ActivityLog::query()->delete();

    // notes is intentionally not an audited attribute for Shipment.
    $shipment->update(['notes' => 'internal remark, not audited']);

    expect(ActivityLog::query()->count())->toBe(0);
});

it('records quotation evaluation creation with the staff actor', function (): void {
    ActivityLog::query()->delete();

    $evaluation = QuotationEvaluation::factory()->recycle($this->team)->create();

    $activity = ActivityLog::query()
        ->where('subject_type', 'quotation_evaluation')
        ->where('subject_id', $evaluation->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->actor_type)->toBe(ActorType::Staff)
        ->and($activity->properties->get('attributes'))->toHaveKey('qe_number', $evaluation->qe_number);
});

it('records a quotation evaluation status change with old and new values', function (): void {
    $evaluation = QuotationEvaluation::factory()->recycle($this->team)->create([
        'status' => QEStatus::NEED_APPROVAL,
    ]);

    ActivityLog::query()->delete();

    $evaluation->update(['status' => QEStatus::APPROVED]);

    $activity = ActivityLog::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('updated')
        ->and($activity->subject_type)->toBe('quotation_evaluation')
        ->and($activity->properties->get('attributes'))->toMatchArray(['status' => QEStatus::APPROVED->value])
        ->and($activity->properties->get('old'))->toMatchArray(['status' => QEStatus::NEED_APPROVAL->value]);
});

it('skips logging when only non-audited quotation evaluation attributes change', function (): void {
    $evaluation = QuotationEvaluation::factory()->recycle($this->team)->create([
        'status' => QEStatus::NEED_APPROVAL,
    ]);

    ActivityLog::query()->delete();

    // description and the derived data snapshot are intentionally not audited.
    $evaluation->update(['description' => 'not audited', 'data' => ['items' => [], 'suppliers' => [], 'request' => []]]);

    expect(ActivityLog::query()->count())->toBe(0);
});

it('records profit and loss creation with the staff actor', function (): void {
    ActivityLog::query()->delete();

    $pnl = ProfitAndLoss::factory()->recycle($this->team)->create();

    $activity = ActivityLog::query()
        ->where('subject_type', 'profit_and_loss')
        ->where('subject_id', $pnl->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->actor_type)->toBe(ActorType::Staff)
        ->and($activity->properties->get('attributes'))->toHaveKey('pnl_number', $pnl->pnl_number);
});

it('records acceptance report creation and skips non-audited note changes', function (): void {
    ActivityLog::query()->delete();

    $report = AcceptanceReport::factory()->recycle($this->team)->create();

    $activity = ActivityLog::query()
        ->where('subject_type', 'acceptance_report')
        ->where('subject_id', $report->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->actor_type)->toBe(ActorType::Staff)
        ->and($activity->properties->get('attributes'))->toHaveKey('report_number', $report->report_number);

    ActivityLog::query()->delete();

    // notes is intentionally not an audited attribute for AcceptanceReport.
    $report->update(['notes' => 'not audited']);

    expect(ActivityLog::query()->count())->toBe(0);
});

it('records goods receive batch creation with the staff actor', function (): void {
    $supplierOrder = SupplierOrder::factory()->recycle($this->team)->create();

    ActivityLog::query()->delete();

    $batch = GoodsReceiveBatch::query()->create([
        'request_id' => $supplierOrder->request_id,
        'supplier_order_id' => $supplierOrder->getKey(),
        'user_id' => $this->admin->id,
        'media_ids' => [1, 2],
    ]);

    $activity = ActivityLog::query()
        ->where('subject_type', $batch->getMorphClass())
        ->where('subject_id', $batch->getKey())
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->actor_type)->toBe(ActorType::Staff)
        ->and($activity->properties->get('attributes'))->toHaveKey('supplier_order_id', $supplierOrder->getKey());
})->skip(
    fn (): bool => ! in_array(GoodsReceiveBatch::class, Relation::morphMap(), true),
    'goods_receive_batch morph alias pending: added concurrently in AppServiceProvider (task A4)'
);
