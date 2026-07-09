<?php

declare(strict_types=1);

namespace App\Services\BuyerPortal;

use App\Enums\RequestStage;
use App\Models\Request;
use App\Services\Portal\RequestStageTimelinePresenter;

final readonly class BuyerRequestStagePresenter
{
    public function __construct(private RequestStageTimelinePresenter $timelinePresenter) {}

    public function label(Request $request): string
    {
        return $this->labelForStage($this->effectiveStage($request));
    }

    public function effectiveStage(Request $request): RequestStage
    {
        return $this->timelinePresenter->effectiveStage($request);
    }

    public function labelForStage(RequestStage $stage): string
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
        return match ($stage) {
            RequestStage::DRAFT => 'gray',
            RequestStage::AWAITING_SUPPLIER_RESPONSE,
            RequestStage::AWAITING_BUYER_CONFIRMATION,
            RequestStage::AWAITING_SHIPMENT => 'warning',
            RequestStage::PREPARING_BUYER_QUOTE,
            RequestStage::PREPARING_SUPPLIER_ORDER,
            RequestStage::GOODS_RECEIVE,
            RequestStage::SHIPPED => 'info',
            RequestStage::DELIVERED,
            RequestStage::PAID,
            RequestStage::COMPLETED => 'success',
            RequestStage::INVOICED => 'warning',
            RequestStage::CANCELLED => 'danger',
        };
    }

    /**
     * @return list<array{stage: RequestStage, label: string, completed: bool, current: bool}>
     */
    public function timeline(Request $request): array
    {
        return $this->timelinePresenter->timeline($request);
    }
}
