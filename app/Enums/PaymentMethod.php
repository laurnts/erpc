<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasIcon, HasLabel
{
    case BANK_TRANSFER = 'bank_transfer';
    case CASH = 'cash';
    case CHECK = 'check';
    case LC = 'lc';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CASH => 'Cash',
            self::CHECK => 'Check',
            self::LC => 'Letter of Credit',
            self::OTHER => 'Other',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'primary',
            self::CASH => 'success',
            self::CHECK => 'info',
            self::LC => 'warning',
            self::OTHER => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'heroicon-o-building-library',
            self::CASH => 'heroicon-o-banknotes',
            self::CHECK => 'heroicon-o-document-check',
            self::LC => 'heroicon-o-shield-check',
            self::OTHER => 'heroicon-o-ellipsis-horizontal-circle',
        };
    }
}
