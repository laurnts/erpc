<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Derived lifecycle state of a portal membership record — never stored:
 * no linked user yet means Invited, a linked user with an active record is
 * Active, a linked user with an inactive record is Deactivated.
 */
enum PortalMembershipState: string implements HasColor, HasLabel
{
    case Invited = 'invited';
    case Active = 'active';
    case Deactivated = 'deactivated';

    public function getLabel(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Active => 'Active',
            self::Deactivated => 'Deactivated',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Invited => 'warning',
            self::Active => 'success',
            self::Deactivated => 'gray',
        };
    }
}
