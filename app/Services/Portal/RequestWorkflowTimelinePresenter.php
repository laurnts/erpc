<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\RequestStage;
use App\Models\Request;

/**
 * Eight-step operator workflow progress bar shared by the buyer and supplier
 * portals. Uses the same tab order and labels as the staff request view.
 */
final readonly class RequestWorkflowTimelinePresenter
{
    /**
     * @return list<array{stage: RequestStage, label: string, completed: bool, current: bool}>
     */
    public function timeline(Request $request, RequestStage $activeStage): array
    {
        if ($request->stage === RequestStage::CANCELLED) {
            return [[
                'stage' => RequestStage::CANCELLED,
                'label' => RequestStage::CANCELLED->getTabLabel(),
                'completed' => false,
                'current' => true,
            ]];
        }

        $milestones = [
            RequestStage::DRAFT,
            RequestStage::AWAITING_SUPPLIER_RESPONSE,
            RequestStage::PREPARING_BUYER_QUOTE,
            RequestStage::PREPARING_SUPPLIER_ORDER,
            RequestStage::GOODS_RECEIVE,
            RequestStage::AWAITING_BUYER_CONFIRMATION,
            RequestStage::AWAITING_SHIPMENT,
            RequestStage::DELIVERED,
        ];
        $resolvedActive = $this->resolveActiveTimelineStage($activeStage);
        $activeStep = $resolvedActive->getTabStep();

        return array_map(
            fn (RequestStage $stage): array => [
                'stage' => $stage,
                'label' => $stage->getTabLabel(),
                'completed' => $activeStep !== null && $stage->getTabStep() < $activeStep,
                'current' => $stage === $resolvedActive,
            ],
            $milestones,
        );
    }

    private function resolveActiveTimelineStage(RequestStage $stage): RequestStage
    {
        return match ($stage) {
            RequestStage::SHIPPED,
            RequestStage::DELIVERED,
            RequestStage::INVOICED,
            RequestStage::PAID,
            RequestStage::COMPLETED => RequestStage::DELIVERED,
            default => $stage,
        };
    }
}
