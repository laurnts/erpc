<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum ContactRole: string implements HasLabel, HasDescription
{
    case PRIMARY = 'primary';
    case BILLING = 'billing';
    case TECHNICAL = 'technical';
    case SALES = 'sales';
    case SUPPORT = 'support';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::PRIMARY => 'Primary Contact',
            self::BILLING => 'Billing Contact',
            self::TECHNICAL => 'Technical Contact',
            self::SALES => 'Sales Contact',
            self::SUPPORT => 'Support Contact',
            self::OTHER => 'Other',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::PRIMARY => 'Main point of contact for the company',
            self::BILLING => 'Handles billing and financial matters',
            self::TECHNICAL => 'Technical contact for product/service issues',
            self::SALES => 'Sales representative or account manager',
            self::SUPPORT => 'Customer support contact',
            self::OTHER => 'Other role or unspecified',
        };
    }
}
