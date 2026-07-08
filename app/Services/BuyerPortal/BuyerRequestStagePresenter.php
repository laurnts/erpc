<?php

declare(strict_types=1);

namespace App\Services\BuyerPortal;

use App\Enums\BuyerQuoteStatus;
use App\Enums\RequestStage;
use App\Models\BuyerQuote;
use App\Models\Request;

final readonly class BuyerRequestStagePresenter
{
    public function label(Request $request): string
    {
        return $this->labelForStage($this->effectiveStage($request));
    }

    public function effectiveStage(Request $request): RequestStage
    {
        if ($request->stage !== RequestStage::PREPARING_BUYER_QUOTE) {
            return $request->stage;
        }

        if ($this->requestHasSentBuyerQuote($request)) {
            return RequestStage::AWAITING_BUYER_CONFIRMATION;
        }

        return $request->stage;
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

    public function timelineLabelForStage(RequestStage $stage): string
    {
        return match ($stage) {
            RequestStage::DRAFT => 'Received',
            RequestStage::AWAITING_SUPPLIER_RESPONSE => 'Sourcing quotes',
            RequestStage::PREPARING_BUYER_QUOTE => 'Quote prepared',
            RequestStage::AWAITING_BUYER_CONFIRMATION => 'Awaiting confirmation',
            RequestStage::PREPARING_SUPPLIER_ORDER,
            RequestStage::GOODS_RECEIVE => 'Processed',
            RequestStage::SHIPPED => 'In transit',
            RequestStage::COMPLETED => 'Completed',
            RequestStage::CANCELLED => 'Cancelled',
            default => $this->labelForStage($stage),
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
        if ($request->stage === RequestStage::CANCELLED) {
            return [[
                'stage' => RequestStage::CANCELLED,
                'label' => $this->labelForStage(RequestStage::CANCELLED),
                'completed' => false,
                'current' => true,
            ]];
        }

        $milestones = [
            RequestStage::DRAFT,
            RequestStage::AWAITING_SUPPLIER_RESPONSE,
            RequestStage::PREPARING_BUYER_QUOTE,
            RequestStage::AWAITING_BUYER_CONFIRMATION,
            RequestStage::PREPARING_SUPPLIER_ORDER,
            RequestStage::SHIPPED,
            RequestStage::COMPLETED,
        ];

        $activeMilestone = $this->resolveActiveMilestone($this->effectiveStage($request));

        return array_map(
            fn (RequestStage $stage): array => [
                'stage' => $stage,
                'label' => $this->timelineLabelForStage($stage),
                'completed' => $stage->getOrder() < $activeMilestone->getOrder(),
                'current' => $stage === $activeMilestone,
            ],
            $milestones,
        );
    }

    private function requestHasSentBuyerQuote(Request $request): bool
    {
        if ($request->relationLoaded('buyerQuotes')) {
            return $request->buyerQuotes->contains(
                fn (BuyerQuote $quote): bool => $quote->status === BuyerQuoteStatus::SENT,
            );
        }

        return $request->buyerQuotes()
            ->where('status', BuyerQuoteStatus::SENT)
            ->exists();
    }

    private function resolveActiveMilestone(RequestStage $stage): RequestStage
    {
        return match (true) {
            $stage->getOrder() <= RequestStage::DRAFT->getOrder() => RequestStage::DRAFT,
            $stage->getOrder() <= RequestStage::AWAITING_SUPPLIER_RESPONSE->getOrder() => RequestStage::AWAITING_SUPPLIER_RESPONSE,
            $stage->getOrder() <= RequestStage::PREPARING_BUYER_QUOTE->getOrder() => RequestStage::PREPARING_BUYER_QUOTE,
            $stage->getOrder() <= RequestStage::AWAITING_BUYER_CONFIRMATION->getOrder() => RequestStage::AWAITING_BUYER_CONFIRMATION,
            $stage->getOrder() <= RequestStage::GOODS_RECEIVE->getOrder() => RequestStage::PREPARING_SUPPLIER_ORDER,
            $stage->getOrder() <= RequestStage::DELIVERED->getOrder() => RequestStage::SHIPPED,
            default => RequestStage::COMPLETED,
        };
    }
}
