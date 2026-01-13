<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InvoiceType: string implements HasColor, HasIcon, HasLabel
{
    case PREPAYMENT = 'prepayment';
    case BALANCE = 'balance';
    case STANDARD = 'standard';
    case CREDIT_NOTE = 'credit_note';
    case DEBIT_NOTE = 'debit_note';

    public function getLabel(): string
    {
        return match ($this) {
            self::PREPAYMENT => 'Prepayment',
            self::BALANCE => 'Balance',
            self::STANDARD => 'Standard',
            self::CREDIT_NOTE => 'Credit Note',
            self::DEBIT_NOTE => 'Debit Note',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PREPAYMENT => 'info',
            self::BALANCE => 'primary',
            self::STANDARD => 'gray',
            self::CREDIT_NOTE => 'success',
            self::DEBIT_NOTE => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PREPAYMENT => 'heroicon-o-arrow-down-on-square',
            self::BALANCE => 'heroicon-o-scale',
            self::STANDARD => 'heroicon-o-document-text',
            self::CREDIT_NOTE => 'heroicon-o-arrow-uturn-left',
            self::DEBIT_NOTE => 'heroicon-o-arrow-uturn-right',
        };
    }
}
