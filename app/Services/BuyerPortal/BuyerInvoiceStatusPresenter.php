<?php

declare(strict_types=1);

namespace App\Services\BuyerPortal;

use App\Enums\InvoiceStatus;

final readonly class BuyerInvoiceStatusPresenter
{
    public function label(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::SENT => 'Received',
            default => $status->getLabel(),
        };
    }

    public function color(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::SENT => 'info',
            default => $status->getColor(),
        };
    }

    public function icon(InvoiceStatus $status): ?string
    {
        return match ($status) {
            InvoiceStatus::SENT => 'heroicon-o-inbox-arrow-down',
            default => $status->getIcon(),
        };
    }
}
