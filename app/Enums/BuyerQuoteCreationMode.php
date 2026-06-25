<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum BuyerQuoteCreationMode: string implements HasDescription, HasLabel
{
    case CONSOLIDATED = 'consolidated';
    case PER_SUPPLIER = 'per_supplier';

    public function getLabel(): string
    {
        return match ($this) {
            self::CONSOLIDATED => 'Single buyer quote',
            self::PER_SUPPLIER => 'Separate buyer quote per supplier',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CONSOLIDATED => 'One buyer quote containing all selected items from the comparison evaluation.',
            self::PER_SUPPLIER => 'One buyer quote per supplier, each containing only that supplier\'s selected items.',
        };
    }
}
