<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum CentralPurchasingRole: string implements HasLabel, HasDescription
{
    case KEY_ACCOUNT = 'key_account';
    case DEPT_HEAD_SALES = 'dept_head_sales';
    case DEPUTY_DIRECTOR = 'deputy_director';
    case DIRECTOR = 'director';
    case FINANCE = 'finance';

    public function getLabel(): string
    {
        return match ($this) {
            self::KEY_ACCOUNT => 'Key Account',
            self::DEPT_HEAD_SALES => 'Dept Head of Sales',
            self::DEPUTY_DIRECTOR => 'Deputy Director',
            self::DIRECTOR => 'Director',
            self::FINANCE => 'Finance',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::KEY_ACCOUNT => 'Key Account - Can be assigned buyers',
            self::DEPT_HEAD_SALES => 'Department Head of Sales - Acknowledges QE/PNL documents',
            self::DEPUTY_DIRECTOR => 'Deputy Director - Acknowledges QE/PNL documents',
            self::DIRECTOR => 'Director - Approves QE/PNL documents',
            self::FINANCE => 'Finance',
        };
    }
}
