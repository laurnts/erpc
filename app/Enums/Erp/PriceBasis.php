<?php

declare(strict_types=1);

namespace App\Enums\Erp;

/**
 * How an entered unit price relates to tax. Each document model maps its own
 * stored flags onto this so a single LineCalculator can serve both the buyer
 * (price always net) and supplier (price may be gross) conventions.
 */
enum PriceBasis: string
{
    case NET = 'net';
    case GROSS = 'gross';
}
