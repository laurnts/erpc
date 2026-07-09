<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\RequestStage;
use App\Models\Request;

/**
 * Eight-step workflow progress bar shared by the buyer and supplier portals.
 * Uses the staff tab labels but renders milestones in chronological workflow
 * order (RequestStage::getOrder), which differs from the staff tab order.
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

        // Chronological workflow order (RequestStage::getOrder), not staff tab
        // order: buyer confirmation happens BEFORE purchasing, so comparing tab
        // steps would tick "Supplier Orders"/"Goods Receive" as done while the
        // buyer is still confirming.
        $milestones = [
            RequestStage::DRAFT,
            RequestStage::AWAITING_SUPPLIER_RESPONSE,
            RequestStage::PREPARING_BUYER_QUOTE,
            RequestStage::AWAITING_BUYER_CONFIRMATION,
            RequestStage::PREPARING_SUPPLIER_ORDER,
            RequestStage::GOODS_RECEIVE,
            RequestStage::AWAITING_SHIPMENT,
            RequestStage::DELIVERED,
        ];
        $resolvedActive = $this->resolveActiveTimelineStage($activeStage);

        return array_map(
            fn (RequestStage $stage): array => [
                'stage' => $stage,
                'label' => $stage->getTabLabel(),
                'completed' => $stage->getOrder() < $resolvedActive->getOrder(),
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
