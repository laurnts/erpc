<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Who a request note is shared WITH.
 *
 * Staff always see every note regardless of this value; the enum only widens
 * a note's audience to a portal side. Internal notes never reach a portal,
 * Buyer notes reach the request's buyer, and Supplier notes reach the single
 * supplier company the note is scoped to (RequestNote::audience_company_id).
 */
enum NoteVisibility: string implements HasColor, HasIcon, HasLabel
{
    case Internal = 'internal';
    case Buyer = 'buyer';
    case Supplier = 'supplier';

    public function getLabel(): string
    {
        return match ($this) {
            self::Internal => 'Internal',
            self::Buyer => 'Shared with buyer',
            self::Supplier => 'Shared with supplier',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Internal => 'gray',
            self::Buyer => 'success',
            self::Supplier => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Internal => 'heroicon-o-lock-closed',
            self::Buyer => 'heroicon-o-shopping-cart',
            self::Supplier => 'heroicon-o-truck',
        };
    }
}
