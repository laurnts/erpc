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
 * The stage a portal user (buyer or supplier) should perceive as current,
 * independent of a possibly stale internal stage. Shared by both portal
 * stage presenters so the two steppers can never disagree.
 */
final readonly class RequestEffectiveStageResolver
{
    /**
     * The stage a buyer should perceive as current. A buyer quote that has
     * been SENT means the request is effectively awaiting confirmation even
     * while the internal stage still reads PREPARING_BUYER_QUOTE. Conversely,
     * a stale `awaiting_buyer_confirmation` internal stage must not block the
     * buyer once their order or quote acceptance is complete.
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
        if ($request->buyerInvoices()->where('status', InvoiceStatus::PAID)->exists()) {
            return RequestStage::PAID;
        }

        $deliveredShipment = $request->shipments()
            ->where('type', ShipmentType::OUTBOUND)
            ->where('status', ShipmentStatus::DELIVERED)
            ->exists();

        if ($deliveredShipment) {
            return RequestStage::DELIVERED;
        }

        $activeShipment = $request->shipments()
            ->where('type', ShipmentType::OUTBOUND)
            ->whereIn('status', [
                ShipmentStatus::PENDING,
                ShipmentStatus::IN_TRANSIT,
                ShipmentStatus::PARTIAL,
            ])
            ->exists();

        if ($activeShipment) {
            return RequestStage::SHIPPED;
        }

        return RequestStage::PREPARING_SUPPLIER_ORDER;
    }
}
