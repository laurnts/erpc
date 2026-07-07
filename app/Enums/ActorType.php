<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Identifies which kind of account performed a logged action.
 *
 * The distinction comes from the panel/guard the actor was authenticated
 * through when the activity was recorded, not from the user record itself
 * (a single User may act as both staff and a portal contact).
 */
enum ActorType: string implements HasColor, HasIcon, HasLabel
{
    case Staff = 'staff';
    case Buyer = 'buyer';
    case Supplier = 'supplier';
    case Admin = 'admin';
    case System = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Buyer => 'Buyer',
            self::Supplier => 'Supplier',
            self::Admin => 'Admin',
            self::System => 'System',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Staff => 'primary',
            self::Buyer => 'success',
            self::Supplier => 'warning',
            self::Admin => 'danger',
            self::System => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Staff => 'heroicon-o-briefcase',
            self::Buyer => 'heroicon-o-shopping-cart',
            self::Supplier => 'heroicon-o-truck',
            self::Admin => 'heroicon-o-shield-check',
            self::System => 'heroicon-o-cpu-chip',
        };
    }
}
