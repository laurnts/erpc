<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\BuyerQuoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RequestStage;
use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Models\BuyerQuote;
use App\Models\Request;

/**
 * Portal-facing request progress stepper shared by the buyer and supplier
 * panels: both render the same seven customer-journey milestones so the two
 * portals stay visually and semantically consistent.
 */
final readonly class RequestStageTimelinePresenter
{
    /**
     * The stage a portal user should perceive as current. A buyer quote that
     * has been SENT means the request is effectively awaiting confirmation
     * even while the internal stage still reads PREPARING_BUYER_QUOTE.
     * Conversely, a stale `awaiting_buyer_confirmation` internal stage must
     * not block the buyer once their order or quote acceptance is complete.
     */
    public function effectiveStage(Request $request): RequestStage
    {
        if ($request->stage === RequestStage::PREPARING_BUYER_QUOTE
            && $this->requestHasSentBuyerQuote($request)) {
            return RequestStage::AWAITING_BUYER_CONFIRMATION;
        }

        if ($request->stage->getOrder() <= RequestStage::AWAITING_BUYER_CONFIRMATION->getOrder()
            && $this->buyerConfirmationIsComplete($request)) {
            return $this->resolvePostConfirmationStage($request);
        }

        return $request->stage;
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
            RequestStage::AWAITING_SHIPMENT,
            RequestStage::SHIPPED => 'In transit',
            RequestStage::DELIVERED => 'Delivered',
            RequestStage::INVOICED,
            RequestStage::PAID,
            RequestStage::COMPLETED => 'Completed',
            RequestStage::CANCELLED => 'Cancelled',
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
                'label' => $this->timelineLabelForStage(RequestStage::CANCELLED),
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

    private function buyerConfirmationIsComplete(Request $request): bool
    {
        if ($request->has_buyer_order_confirmed) {
            return true;
        }

        if ($request->relationLoaded('buyerQuotes')) {
            return $request->buyerQuotes->contains(
                fn (BuyerQuote $quote): bool => $quote->status === BuyerQuoteStatus::ACCEPTED,
            );
        }

        return $request->buyerQuotes()
            ->where('status', BuyerQuoteStatus::ACCEPTED)
            ->exists();
    }

    private function resolvePostConfirmationStage(Request $request): RequestStage
    {
        if ($request->stage->getOrder() > RequestStage::AWAITING_BUYER_CONFIRMATION->getOrder()) {
            return $request->stage;
        }

        if ($this->requestHasPaidBuyerInvoice($request)) {
            return RequestStage::PAID;
        }

        if ($this->requestHasDeliveredOutboundShipment($request)) {
            return RequestStage::DELIVERED;
        }

        if ($this->requestHasInTransitOutboundShipment($request)) {
            return RequestStage::SHIPPED;
        }

        return RequestStage::PREPARING_SUPPLIER_ORDER;
    }

    private function requestHasPaidBuyerInvoice(Request $request): bool
    {
        return $request->buyerInvoices()
            ->where('status', InvoiceStatus::PAID)
            ->exists();
    }

    private function requestHasDeliveredOutboundShipment(Request $request): bool
    {
        return $request->shipments()
            ->where('type', ShipmentType::OUTBOUND)
            ->where('status', ShipmentStatus::DELIVERED)
            ->exists();
    }

    private function requestHasInTransitOutboundShipment(Request $request): bool
    {
        return $request->shipments()
            ->where('type', ShipmentType::OUTBOUND)
            ->whereIn('status', [
                ShipmentStatus::PENDING,
                ShipmentStatus::IN_TRANSIT,
                ShipmentStatus::PARTIAL,
            ])
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
