<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RequestSubmissionMethod: string implements HasColor, HasLabel
{
    case MANUAL = 'manual';
    case DOCUMENT = 'document';

    public function getLabel(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual Entry',
            self::DOCUMENT => 'Document Upload',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MANUAL => 'info',
            self::DOCUMENT => 'warning',
        };
    }
}
