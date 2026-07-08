<?php

declare(strict_types=1);

namespace App\Enums;

enum TimelineHistoryFilter: string
{
    case All = 'all';
    case Approvals = 'approvals';
    case Documents = 'documents';
    case Quotes = 'quotes';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Approvals => 'Approvals',
            self::Documents => 'Documents',
            self::Quotes => 'Quotes',
        };
    }

    /**
     * @return list<self>
     */
    public static function chips(): array
    {
        return [
            self::All,
            self::Approvals,
            self::Documents,
            self::Quotes,
        ];
    }
}
