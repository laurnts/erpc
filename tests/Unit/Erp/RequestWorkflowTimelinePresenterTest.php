<?php

declare(strict_types=1);

use App\Enums\RequestStage;
use App\Models\Request;
use App\Services\Portal\RequestWorkflowTimelinePresenter;

function workflowTimelineFor(RequestStage $stage): array
{
    $request = new Request;
    $request->stage = $stage;

    return app(RequestWorkflowTimelinePresenter::class)->timeline($request, $stage);
}

it('does not mark supplier orders or goods receive complete while the buyer is still confirming', function (): void {
    $timeline = collect(workflowTimelineFor(RequestStage::AWAITING_BUYER_CONFIRMATION))
        ->keyBy(fn (array $milestone): string => $milestone['stage']->value);

    expect($timeline->get(RequestStage::PREPARING_SUPPLIER_ORDER->value)['completed'])->toBeFalse()
        ->and($timeline->get(RequestStage::GOODS_RECEIVE->value)['completed'])->toBeFalse()
        ->and($timeline->get(RequestStage::AWAITING_BUYER_CONFIRMATION->value)['current'])->toBeTrue()
        ->and($timeline->get(RequestStage::DRAFT->value)['completed'])->toBeTrue()
        ->and($timeline->get(RequestStage::PREPARING_BUYER_QUOTE->value)['completed'])->toBeTrue();
});

it('renders milestones in workflow order so completion is monotonic', function (): void {
    $orders = array_map(
        fn (array $milestone): int => $milestone['stage']->getOrder(),
        workflowTimelineFor(RequestStage::DRAFT),
    );

    $sorted = $orders;
    sort($sorted);

    expect($orders)->toBe($sorted);
});

it('marks buyer confirmation complete once goods are being received', function (): void {
    $timeline = collect(workflowTimelineFor(RequestStage::GOODS_RECEIVE))
        ->keyBy(fn (array $milestone): string => $milestone['stage']->value);

    expect($timeline->get(RequestStage::AWAITING_BUYER_CONFIRMATION->value)['completed'])->toBeTrue()
        ->and($timeline->get(RequestStage::GOODS_RECEIVE->value)['current'])->toBeTrue()
        ->and($timeline->get(RequestStage::AWAITING_SHIPMENT->value)['completed'])->toBeFalse();
});

it('collapses shipped and later stages onto the delivered milestone', function (): void {
    $timeline = collect(workflowTimelineFor(RequestStage::PAID))
        ->keyBy(fn (array $milestone): string => $milestone['stage']->value);

    expect($timeline->get(RequestStage::DELIVERED->value)['current'])->toBeTrue()
        ->and($timeline->get(RequestStage::AWAITING_SHIPMENT->value)['completed'])->toBeTrue();
});

it('shows only a cancelled milestone for cancelled requests', function (): void {
    $timeline = workflowTimelineFor(RequestStage::CANCELLED);

    expect($timeline)->toHaveCount(1)
        ->and($timeline[0]['stage'])->toBe(RequestStage::CANCELLED)
        ->and($timeline[0]['current'])->toBeTrue();
});

it('derives milestones from the enum: workflow-ordered, one per tab step', function (): void {
    $milestones = \App\Enums\RequestStage::portalMilestones();

    $orders = array_map(fn (\App\Enums\RequestStage $stage): int => $stage->getOrder(), $milestones);
    $sorted = $orders;
    sort($sorted);

    $tabSteps = array_map(fn (\App\Enums\RequestStage $stage): ?int => $stage->getTabStep(), $milestones);

    expect($orders)->toBe($sorted)
        ->and($tabSteps)->toBe([1, 2, 3, 6, 4, 5, 7, 8])
        ->and($milestones)->toHaveCount(8);
});
