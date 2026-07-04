<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SupplierQuoteSubmissionMethod: string implements HasLabel
{
    case Internal = 'internal';
    case Portal = 'portal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Internal => 'Entered Internally',
            self::Portal => 'Supplier Portal',
        };
    }
}
