<?php

declare(strict_types=1);

namespace App\Services\BuyerPortal;

use App\Enums\BuyerQuoteStatus;
use App\Enums\RequestStage;
use App\Models\BuyerQuote;
use App\Models\Request;
use App\Services\Portal\RequestWorkflowTimelinePresenter;

final readonly class BuyerRequestStagePresenter
{
    public function __construct(private RequestWorkflowTimelinePresenter $timelinePresenter) {}
    public function label(Request $request): string
    {
        return $this->labelForStage($this->effectiveStage($request));
    }

    public function effectiveStage(Request $request): RequestStage
    {
        if ($request->stage === RequestStage::PREPARING_BUYER_QUOTE
            && $this->requestHasSentBuyerQuote($request)) {
            return RequestStage::AWAITING_BUYER_CONFIRMATION;
        }

        return $request->stage;
    }

    public function labelForStage(RequestStage $stage): string
    {
        return $stage->getTabLabelWithStep();
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
        return $this->timelinePresenter->timeline($request, $this->effectiveStage($request));
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

}
