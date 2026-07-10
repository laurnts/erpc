<?php

declare(strict_types=1);

namespace App\Services\BuyerPortal;

use App\Enums\RequestStage;
use App\Models\Request;
use App\Services\Portal\RequestEffectiveStageResolver;
use App\Services\Portal\RequestWorkflowTimelinePresenter;

final readonly class BuyerRequestStagePresenter
{
    public function __construct(
        private RequestWorkflowTimelinePresenter $timelinePresenter,
        private RequestEffectiveStageResolver $stageResolver,
    ) {}

    public function label(Request $request): string
    {
        return $this->labelForStage($this->effectiveStage($request));
    }

    public function effectiveStage(Request $request): RequestStage
    {
        return $this->stageResolver->effectiveStage($request);
    }

    public function labelForStage(RequestStage $stage): string
    {
        return $stage->getTabLabelWithStep();
    }

    /**
     * Customer-safe stage vocabulary for surfaces that must not expose the
     * internal sourcing workflow (redacted timelines, buyer emails). Portal
     * pages intentionally use the staff tab labels via labelForStage().
     */
    public function partyFacingLabel(RequestStage $stage): string
    {
        return match ($stage) {
            RequestStage::DRAFT => 'Request Received',
            RequestStage::AWAITING_SUPPLIER_RESPONSE => 'Sourcing Quotes',
            RequestStage::PREPARING_BUYER_QUOTE => 'Quote Being Prepared',
            RequestStage::AWAITING_BUYER_CONFIRMATION => 'Awaiting Your Confirmation',
            RequestStage::PREPARING_SUPPLIER_ORDER,
            RequestStage::GOODS_RECEIVE => 'Being Processed',
            RequestStage::AWAITING_SHIPMENT,
            RequestStage::SHIPPED => 'In Transit',
            RequestStage::DELIVERED => 'Delivered',
            RequestStage::INVOICED,
            RequestStage::PAID,
            RequestStage::COMPLETED => 'Completed',
            RequestStage::CANCELLED => 'Cancelled',
        };
    }

    public function color(RequestStage $stage): string
    {
        return $stage->getColor();
    }

    /**
     * @return list<array{stage: RequestStage, label: string, completed: bool, current: bool}>
     */
    public function timeline(Request $request): array
    {
        return $this->timelinePresenter->timeline($request, $this->effectiveStage($request));
    }
}
