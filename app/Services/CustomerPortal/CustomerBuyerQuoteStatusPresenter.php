<?php

declare(strict_types=1);

namespace App\Services\CustomerPortal;

use App\Enums\BuyerQuoteStatus;

final readonly class CustomerBuyerQuoteStatusPresenter
{
    public function label(BuyerQuoteStatus $status): string
    {
        return match ($status) {
            BuyerQuoteStatus::SENT => 'Awaiting Your Confirmation',
            BuyerQuoteStatus::ACCEPTED => 'Accepted',
            BuyerQuoteStatus::REJECTED => 'Rejected',
            BuyerQuoteStatus::EXPIRED => 'Expired',
            BuyerQuoteStatus::SUPERSEDED => 'Superseded',
            default => $status->getLabel(),
        };
    }

    public function color(BuyerQuoteStatus $status): string
    {
        return match ($status) {
            BuyerQuoteStatus::SENT => 'warning',
            default => $status->getColor(),
        };
    }

    public function icon(BuyerQuoteStatus $status): ?string
    {
        return match ($status) {
            BuyerQuoteStatus::SENT => 'heroicon-o-clock',
            default => $status->getIcon(),
        };
    }
}
